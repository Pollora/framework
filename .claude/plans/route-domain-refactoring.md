# Étude : Refactoring Route.php — Découplage Domain/Infrastructure

## Problème

`src/Route/Domain/Models/Route.php` extends `Illuminate\Routing\Route` directement dans la couche Domain. C'est la dernière violation DDD majeure du framework.

### Pourquoi c'est structurant

- Le Domain routing est couplé à l'implémentation Laravel
- 13 fichiers (5 src + 8 tests) référencent directement cette classe
- 4 middlewares font des `instanceof Route` checks
- Le `matches()` override contient la logique métier WordPress (condition functions)
- `ExtendedRouter` remplace le router Laravel pour créer nos instances Route
- Les macros `Route::wp()` et `Route::wpMatch()` manipulent directement les properties WordPress

### Ce que Route.php ajoute à IlluminateRoute

**4 properties WordPress :**
- `$isWordPressRoute: bool`
- `$condition: string` (ex: `is_single`, `is_page`)
- `$conditionParameters: array`
- `$conditionResolver: ConditionResolverInterface`

**8 méthodes WordPress :**
- `setIsWordPressRoute()` / `isWordPressRoute()`
- `setCondition()` / `getCondition()` / `hasCondition()`
- `setConditionParameters()` / `getConditionParameters()`
- `setConditionResolver()`

**1 override :**
- `matches()` — diverge entre matching WordPress (condition function) et matching Laravel (URI pattern)

**Méthodes héritées utilisées par les consumers :**
- `getAction()`, `setParameter()`, `parameter()`, `hasParameter()`, `uri()`, `getCompiled()`, `middleware()`

---

## Approche recommandée : Composition + Interface

### Étape 1 — Créer `WordPressRouteInterface` (Domain)

```php
// src/Route/Domain/Contracts/WordPressRouteInterface.php
interface WordPressRouteInterface
{
    public function isWordPressRoute(): bool;
    public function setIsWordPressRoute(bool $isWordPressRoute): static;
    public function getCondition(): string;
    public function setCondition(string $condition): static;
    public function hasCondition(): bool;
    public function getConditionParameters(): array;
    public function setConditionParameters(array $parameters): static;
    public function setConditionResolver(ConditionResolverInterface $resolver): static;
}
```

### Étape 2 — Déplacer Route vers Infrastructure

```
src/Route/Domain/Models/Route.php
  → src/Route/Infrastructure/Models/Route.php
```

Le namespace change : `Pollora\Route\Infrastructure\Models\Route`.

La classe reste identique (extends IlluminateRoute + implements WordPressRouteInterface). Le couplage Laravel est désormais dans la couche Infrastructure où il a sa place.

### Étape 3 — Mettre à jour les imports (13 fichiers)

| Fichier | Action |
|---------|--------|
| `ExtendedRouter.php` | Update import |
| `WordPressRoutingService.php` | Type-hint → `WordPressRouteInterface` |
| `WordPressBindings.php` | `instanceof WordPressRouteInterface` |
| `WordPressBodyClass.php` | `instanceof WordPressRouteInterface` — **attention** : utilise aussi des méthodes héritées Laravel (`getCompiled`, `parameter`, `hasParameter`) |
| `WordPressHeaders.php` | `method_exists` → `instanceof WordPressRouteInterface` |
| `BindWordPressParametersUseCase.php` | Type-hint → `WordPressRouteInterface` — **attention** : utilise `getAction()`, `setParameter()`, `uri()` |
| `RouteServiceProvider.php` | Macros utilisent `$route->setIsWordPressRoute()` etc. |
| 8 fichiers tests | Update imports |

### Étape 4 — Problème `BindWordPressParametersUseCase` (Application layer)

Ce use case est dans la couche Application et utilise :
- `$route->getAction()` — méthode de IlluminateRoute, pas de WordPressRouteInterface
- `$route->setParameter()` — idem
- `$route->uri()` — idem

**Options :**
1. **Étendre l'interface** avec ces méthodes (mais ça tire les concepts Laravel dans le Domain)
2. **Créer une interface `RoutableInterface`** dans Domain avec `getAction()`, `setParameter()`, `uri()`, `parameter()`, `hasParameter()` — ces concepts sont assez génériques pour exister au niveau Domain
3. **Garder le type Laravel `Illuminate\Routing\Route`** dans le use case (le use case est Application, pas Domain — acceptable)

**Recommandation :** Option 3 pour l'instant. Le use case accepte `Illuminate\Routing\Route` directement — c'est la couche Application, elle peut dépendre de l'Infrastructure. Seul le Domain doit rester pur.

### Étape 5 — Problème `WordPressBodyClass` middleware

Utilise des méthodes Laravel (`getCompiled()`, `parameter()`, `hasParameter()`) :
- C'est un middleware Infrastructure — acceptable d'utiliser le type concret `Route`
- Ou bien : type-hint `Illuminate\Routing\Route` pour les méthodes Laravel + `instanceof WordPressRouteInterface` pour les méthodes WordPress

### Étape 6 — Backward compatibility

Ajouter un class_alias dans `RouteServiceProvider` :
```php
if (! class_exists('Pollora\\Route\\Domain\\Models\\Route')) {
    class_alias(Route::class, 'Pollora\\Route\\Domain\\Models\\Route');
}
```

---

## Impact estimé

| Métrique | Valeur |
|----------|--------|
| Fichiers source modifiés | 7 |
| Fichiers tests modifiés | 8 |
| Nouveau fichier | 1 (WordPressRouteInterface) |
| Risque | **Moyen** — le routing est critique, mais le changement est structurel (move + interface), pas fonctionnel |
| Effort | ~1-2h |
| Bénéfice | Domain 100% pur des imports `Illuminate\Routing\*` |

## Résultat après refactoring

### Violations Domain `Illuminate\*` restantes

| Fichier | Import | Status |
|---------|--------|--------|
| `ModuleCollection.php` | extends `Collection` | **Accept** — Collection est quasi-standard |
| `PluginCollection.php` | extends `Collection` | **Accept** — idem |
| `DiscoveryEngineInterface.php` | retourne `Collection` | **Accept** — interface publique |
| ~~`Route.php`~~ | ~~extends `IlluminateRoute`~~ | ✅ **Résolu** — déplacé en Infrastructure |

**Domain violations : 4 → 3** (les 3 restantes sont toutes `Collection`, un trade-off accepté).

---

## Pré-requis

- Branche `release/v13.4.0` ou nouvelle branche feature
- 999 tests doivent continuer à passer
- PHPStan 0 erreurs
- Pint clean
- 67 tests intégration skeleton verts

## Vérification

```bash
ddev exec --dir /var/www/html/vendor/pollora/framework composer test
ddev composer test:integration
```

## Ordre d'implémentation suggéré

1. Créer `WordPressRouteInterface` dans Domain/Contracts
2. Faire implémenter l'interface par `Route.php`
3. `git mv` Route.php vers Infrastructure/Models
4. Mettre à jour les 5 fichiers source (imports, type-hints)
5. Ajouter le class_alias backward compat
6. Mettre à jour les 8 fichiers tests
7. Mettre à jour le baseline PHPStan (les macros sont dans le baseline)
8. Vérifier les 999 tests + 67 intégration