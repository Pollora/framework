# Système de Découverte Pollora

Le système de découverte Pollora permet de découvrir automatiquement les classes dans votre application et de les traiter selon leurs types.

## Nouveauté : Traitement Automatique avec `HandlerScoutInterface`

### Vue d'ensemble

Avec la nouvelle interface `HandlerScoutInterface`, les scouts peuvent maintenant définir une méthode `handle()` qui sera automatiquement appelée après la découverte des classes. Cela permet de centraliser la logique de traitement directement dans les scouts.

### Interface `HandlerScoutInterface`

```php
interface HandlerScoutInterface
{
    /**
     * Traite les classes découvertes.
     *
     * @param Collection<int, string> $discoveredClasses Collection des noms de classes découvertes
     * @return void
     */
    public function handle(Collection $discoveredClasses): void;
}
```

### Exemple d'implémentation : PostTypeClassesScout

```php
final class PostTypeClassesScout extends AbstractPolloraScout implements HandlerScoutInterface
{
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractPostType::class);
    }

    public function handle(Collection $discoveredClasses): void
    {
        if ($discoveredClasses->isEmpty()) {
            return;
        }

        $processor = new AttributeProcessor($this->container);
        $postTypeService = $this->container->make(PostTypeService::class);

        foreach ($discoveredClasses as $postTypeClass) {
            $this->registerPostType($postTypeClass, $processor, $postTypeService);
        }
    }

    private function registerPostType(string $postTypeClass, AttributeProcessor $processor, PostTypeService $postTypeService): void
    {
        $postTypeInstance = $this->container->make($postTypeClass);
        
        if (!$postTypeInstance instanceof AbstractPostType) {
            return;
        }

        // Process attributes
        $processor->process($postTypeInstance);

        // Register with WordPress
        $postTypeService->register(
            $postTypeInstance->getSlug(),
            $postTypeInstance->getName(),
            $postTypeInstance->getPluralName(),
            $postTypeInstance->getArgs()
        );
    }
}
```

## Utilisation

### API de base (découverte seulement)

```php
// Découvrir les classes sans traitement
$postTypes = PolloraDiscover::scout('post_types');
```

### API avec traitement automatique

```php
// Découvrir et traiter automatiquement si le scout implémente HandlerScoutInterface
$postTypes = PolloraDiscover::scoutAndHandle('post_types');
```

### Traitement automatique via le DiscovererBootstrapServiceProvider

Le `DiscovererBootstrapServiceProvider` exécute automatiquement `scoutAndHandle()` pour tous les scouts enregistrés au démarrage de l'application. Cela signifie que :

1. **Post Types** : Découverts et enregistrés automatiquement avec WordPress
2. **Taxonomies** : Découvertes et enregistrées automatiquement avec WordPress  
3. **Hooks** : Découverts et leurs attributs traités automatiquement
4. **Attributables** : Découverts et leurs attributs traités automatiquement

## Avantages du nouveau système

### 1. Centralisation de la logique
- La logique de découverte ET de traitement est centralisée dans le scout
- Plus besoin de service providers séparés pour le traitement

### 2. Simplification
- Les anciens `PostTypeAttributeServiceProvider` et `TaxonomyAttributeServiceProvider` deviennent obsolètes
- Moins de code à maintenir

### 3. Flexibilité
- Les scouts peuvent choisir d'implémenter ou non `HandlerScoutInterface`
- Compatibilité avec l'ancien système maintenue

### 4. Performance
- Traitement en une seule passe (découverte + traitement)
- Évite les appels multiples au système de découverte

## Migration

### Avant (ancien système)
```php
// Dans un service provider
public function boot(): void
{
    $postTypeClasses = PolloraDiscover::scout('post_types');
    
    if ($postTypeClasses->isEmpty()) {
        return;
    }

    $processor = new AttributeProcessor($this->app);

    foreach ($postTypeClasses as $postTypeClass) {
        $this->registerPostType($postTypeClass, $processor);
    }
}
```

### Après (nouveau système)
```php
// Dans le scout
public function handle(Collection $discoveredClasses): void
{
    if ($discoveredClasses->isEmpty()) {
        return;
    }

    $processor = new AttributeProcessor($this->container);

    foreach ($discoveredClasses as $postTypeClass) {
        $this->registerPostType($postTypeClass, $processor);
    }
}
```

## Scouts disponibles avec traitement automatique

- `PostTypeClassesScout` : Découvre et enregistre les post types
- `TaxonomyClassesScout` : Découvre et enregistre les taxonomies
- `HookClassesScout` : Découvre et traite les hooks WordPress
- `AttributableClassesScout` : Découvre et traite les classes attributables

## Création d'un nouveau scout avec traitement

```php
final class MyCustomScout extends AbstractPolloraScout implements HandlerScoutInterface
{
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->implementing(MyInterface::class);
    }

    public function handle(Collection $discoveredClasses): void
    {
        foreach ($discoveredClasses as $className) {
            // Votre logique de traitement ici
            $instance = $this->container->make($className);
            // ...
        }
    }
}
```

Puis l'enregistrer :

```php
PolloraDiscover::register('my_custom', MyCustomScout::class);
``` 