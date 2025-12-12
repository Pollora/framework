# Spécifications fonctionnelles : Hook de pré-enregistrement pour Post Types et Taxonomies

## Contexte

Le framework Pollora utilise un système d'attributs PHP pour déclarer des Custom Post Types et des Taxonomies WordPress. Les services `PostTypeDiscovery` et `TaxonomyDiscovery` découvrent et enregistrent automatiquement ces entités. Actuellement, il n'existe pas de mécanisme permettant aux classes déclarantes d'interagir avec le service d'enregistrement juste avant la finalisation.

Les deux services de découverte partagent une structure quasi identique et suivent une architecture hexagonale. Cette évolution doit respecter ce pattern en introduisant une abstraction commune.

## Objectif

Introduire une méthode optionnelle `configuring` que les classes de post type et de taxonomy peuvent implémenter pour interagir avec leur service d'enregistrement respectif avant l'enregistrement final. L'implémentation doit être factorisée via un trait partagé pour éviter la duplication de code.

## Nomenclature

La méthode s'appellera `configuring`. Ce nom suit la convention Laravel des méthodes de lifecycle appelées automatiquement par le framework (comme `booting`, `booted` sur les modèles Eloquent). Le participe présent indique une action en cours, juste avant la finalisation de l'enregistrement.

---

## Architecture proposée

### Structure des fichiers

```
src/
├── Discovery/
│   └── Domain/
│       ├── Contracts/
│       │   └── ConfigurableDiscoveryInterface.php    # Nouveau contrat
│       └── Services/
│           └── HasConfiguringSupport.php             # Nouveau trait
├── PostType/
│   ├── Domain/
│   │   └── Contracts/
│   │       └── PostTypeServiceInterface.php          # Existant
│   └── Infrastructure/
│       └── Services/
│           └── PostTypeDiscovery.php                 # À modifier
└── Taxonomy/
    ├── Domain/
    │   └── Contracts/
    │       └── TaxonomyServiceInterface.php          # Existant
    └── Infrastructure/
        └── Services/
            └── TaxonomyDiscovery.php                 # À modifier
```

---

## Spécifications techniques

### 1. Contrat ConfigurableDiscoveryInterface

**Fichier** : `src/Discovery/Domain/Contracts/ConfigurableDiscoveryInterface.php`

Ce contrat définit le comportement attendu pour les services de découverte supportant la méthode `configuring`.

```php
<?php

declare(strict_types=1);

namespace Pollora\Discovery\Domain\Contracts;

/**
 * Contract for discovery services that support the configuring lifecycle hook.
 *
 * Discovery services implementing this interface allow discovered classes
 * to interact with the registration service before final registration.
 */
interface ConfigurableDiscoveryInterface
{
    /**
     * Get the registration service instance.
     *
     * This service will be passed to the configuring method of discovered classes.
     *
     * @return object The registration service (PostTypeServiceInterface, TaxonomyServiceInterface, etc.)
     */
    public function getRegistrationService(): object;
}
```

### 2. Trait HasConfiguringSupport

**Fichier** : `src/Discovery/Domain/Services/HasConfiguringSupport.php`

Ce trait encapsule la logique de détection et d'appel de la méthode `configuring`. Il utilise le pool d'instances existant pour garantir la cohérence.

```php
<?php

declare(strict_types=1);

namespace Pollora\Discovery\Domain\Services;

/**
 * Trait providing support for the configuring lifecycle hook.
 *
 * This trait can be used by discovery services to call an optional
 * configuring method on discovered classes before registration.
 *
 * Classes using this trait must:
 * - Use the HasInstancePool trait
 * - Implement ConfigurableDiscoveryInterface
 */
trait HasConfiguringSupport
{
    /**
     * Call the configuring method on the class instance if it exists.
     *
     * This method is called just before registration, allowing the class
     * to perform additional configuration using the registration service.
     *
     * @param string $className The fully qualified class name to process
     */
    protected function processConfiguring(string $className): void
    {
        // Implementation details in technical specs below
    }
}
```

### 3. Signature de la méthode configuring

Pour les **Post Types** :
```php
public function configuring(PostTypeServiceInterface $postTypeService): void
```

Pour les **Taxonomies** :
```php
public function configuring(TaxonomyServiceInterface $taxonomyService): void
```

Le type du paramètre dépend du service de découverte qui appelle la méthode. Les classes peuvent typer leur paramètre avec l'interface spécifique pour bénéficier de l'autocomplétion.

---

## Comportement attendu

### Règles générales

1. La méthode `configuring` est **optionnelle** — les classes ne sont pas obligées de l'implémenter
2. Si la méthode existe, elle est appelée :
    - **Après** le traitement de tous les attributs (classe et méthodes)
    - **Après** l'appel à `withArgs()`
    - **Avant** l'appel à `register()`
3. La méthode reçoit l'instance du service d'enregistrement approprié en paramètre
4. La méthode ne retourne rien (`void`)
5. L'instance de la classe est obtenue via le pool d'instances existant (`getInstanceFromPool`)

### Flux d'exécution mis à jour

**Flux actuel** (identique pour PostType et Taxonomy) :
1. Construction de la configuration de base
2. Traitement des attributs de classe
3. Traitement des attributs de méthode
4. Appel de `withArgs()` si présent
5. Enregistrement via `register()`

**Nouveau flux** :
1. Construction de la configuration de base
2. Traitement des attributs de classe
3. Traitement des attributs de méthode
4. Appel de `withArgs()` si présent
5. **Appel de `configuring()` si présent** ← nouveau
6. Enregistrement via `register()`

---

## Implémentation détaillée du trait

### Logique de processConfiguring

```php
protected function processConfiguring(string $className): void
{
    try {
        $reflectionClass = new \ReflectionClass($className);

        if (!$reflectionClass->isInstantiable()) {
            return;
        }

        // Réutiliser l'instance du pool pour cohérence avec withArgs
        $instance = $this->getInstanceFromPool(
            $className, 
            fn () => $reflectionClass->newInstance()
        );

        if (!method_exists($instance, 'configuring')) {
            return;
        }

        // Appeler configuring avec le service d'enregistrement
        $instance->configuring($this->getRegistrationService());

    } catch (\ReflectionException|\Throwable $e) {
        error_log(
            "Failed to process configuring for {$className}: " . $e->getMessage()
        );
    }
}
```

---

## Modifications des services de découverte

### PostTypeDiscovery

1. Ajouter l'implémentation de `ConfigurableDiscoveryInterface`
2. Ajouter l'utilisation du trait `HasConfiguringSupport`
3. Implémenter la méthode `getRegistrationService()`
4. Modifier `processPostType()` pour appeler `processConfiguring()`

```php
final class PostTypeDiscovery implements DiscoveryInterface, ConfigurableDiscoveryInterface
{
    use HasInstancePool, IsDiscovery, HasConfiguringSupport;

    // ...

    public function getRegistrationService(): PostTypeServiceInterface
    {
        return $this->postTypeService;
    }

    private function processPostType(string $className): void
    {
        // ... code existant ...

        // Get additional arguments from the class instance if it has a withArgs method
        $this->processAdditionalArgs($className, $config);

        // Call configuring method if present
        $this->processConfiguring($className);

        // Register the post type
        $this->postTypeService->register(/* ... */);
    }
}
```

### TaxonomyDiscovery

Modifications identiques à PostTypeDiscovery, adaptées pour le contexte Taxonomy :

```php
final class TaxonomyDiscovery implements DiscoveryInterface, ConfigurableDiscoveryInterface
{
    use HasInstancePool, IsDiscovery, HasConfiguringSupport;

    // ...

    public function getRegistrationService(): TaxonomyServiceInterface
    {
        return $this->taxonomyService;
    }

    private function processTaxonomy(string $className): void
    {
        // ... code existant ...

        // Get additional arguments from the class instance if it has a withArgs method
        $this->processAdditionalArgs($className, $config);

        // Call configuring method if present
        $this->processConfiguring($className);

        // Register the taxonomy
        $this->taxonomyService->register(/* ... */);
    }
}
```

---

## Exemples d'utilisation

### Post Type avec configuring

```php
<?php

namespace App\Cms\PostTypes;

use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\PubliclyQueryable;
use Pollora\Attributes\PostType\HasArchive;
use Pollora\Attributes\PostType\Supports;
use Pollora\Attributes\PostType\MenuIcon;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;

#[PostType]
#[PubliclyQueryable]
#[HasArchive]
#[Supports(['title', 'editor', 'thumbnail'])]
#[MenuIcon('dashicons-calendar')]
class Event
{
    public function configuring(PostTypeServiceInterface $postTypeService): void
    {
        // Ajouter des colonnes admin personnalisées
        $postTypeService->addAdminColumn('event', 'event_date', 'Date', function($postId) {
            return get_post_meta($postId, '_event_date', true);
        });

        // Ajouter un hook de sauvegarde
        $postTypeService->onSave('event', function($postId, $post) {
            // Logique métier personnalisée
        });
    }
}
```

### Taxonomy avec configuring

```php
<?php

namespace App\Cms\Taxonomies;

use Pollora\Attributes\Taxonomy;
use Pollora\Attributes\Taxonomy\Hierarchical;
use Pollora\Attributes\Taxonomy\ShowAdminColumn;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyServiceInterface;

#[Taxonomy(objectType: ['event'])]
#[Hierarchical]
#[ShowAdminColumn]
class EventCategory
{
    public function configuring(TaxonomyServiceInterface $taxonomyService): void
    {
        // Ajouter des champs personnalisés au formulaire de terme
        $taxonomyService->addTermFields('event-category', [
            'color' => ['type' => 'color', 'label' => 'Couleur de catégorie'],
        ]);

        // Configurer le comportement de tri
        $taxonomyService->setDefaultOrder('event-category', 'name', 'asc');
    }
}
```

### Classe sans configuring (rétrocompatibilité)

```php
<?php

namespace App\Cms\PostTypes;

use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\Supports;

#[PostType]
#[Supports(['title', 'editor'])]
class SimplePost
{
    // Pas de méthode configuring - fonctionne normalement
}
```

---

## Gestion des erreurs

- Si la méthode `configuring` lève une exception, celle-ci est capturée et loggée via `error_log()`
- L'enregistrement du post type ou de la taxonomy **continue** malgré l'erreur
- Les autres entités découvertes ne sont pas impactées
- Le message d'erreur inclut le nom de la classe pour faciliter le débogage

Format du message d'erreur :
```
Failed to process configuring for App\Cms\PostTypes\Event: [message d'exception]
```

---

## Tests à implémenter

### Tests unitaires pour le trait HasConfiguringSupport

1. **Test sans méthode configuring** : vérifier que le traitement continue normalement
2. **Test avec méthode configuring** : vérifier que la méthode est appelée avec le bon service
3. **Test de gestion d'erreur** : vérifier qu'une exception est loggée sans interrompre le flux
4. **Test de classe non instanciable** : vérifier que les classes abstraites sont ignorées

### Tests d'intégration pour PostTypeDiscovery

1. **Test d'ordre d'exécution** : vérifier que `configuring` est appelée après `withArgs` mais avant `register`
2. **Test de cohérence d'instance** : vérifier que la même instance est utilisée pour `withArgs` et `configuring`
3. **Test de type de service** : vérifier que `PostTypeServiceInterface` est bien passé

### Tests d'intégration pour TaxonomyDiscovery

1. **Test d'ordre d'exécution** : vérifier que `configuring` est appelée après `withArgs` mais avant `register`
2. **Test de cohérence d'instance** : vérifier que la même instance est utilisée pour `withArgs` et `configuring`
3. **Test de type de service** : vérifier que `TaxonomyServiceInterface` est bien passé

### Tests de rétrocompatibilité

1. **Test Post Type sans configuring** : vérifier le fonctionnement normal
2. **Test Taxonomy sans configuring** : vérifier le fonctionnement normal
3. **Test avec withArgs uniquement** : vérifier que withArgs fonctionne toujours

---

## Rétrocompatibilité

Cette modification est entièrement rétrocompatible :
- Les classes existantes sans méthode `configuring` continuent de fonctionner sans modification
- La méthode `withArgs()` existante n'est pas impactée
- L'ordre de priorité des configurations reste inchangé (attributs → withArgs → configuring → register)

---

## Résumé des fichiers à créer/modifier

| Fichier | Action | Description |
|---------|--------|-------------|
| `src/Discovery/Domain/Contracts/ConfigurableDiscoveryInterface.php` | Créer | Contrat pour les services supportant configuring |
| `src/Discovery/Domain/Services/HasConfiguringSupport.php` | Créer | Trait avec la logique de processConfiguring |
| `src/PostType/Infrastructure/Services/PostTypeDiscovery.php` | Modifier | Ajouter interface, trait et appel |
| `src/Taxonomy/Infrastructure/Services/TaxonomyDiscovery.php` | Modifier | Ajouter interface, trait et appel |
