# Spécifications Techniques : Système de Logging Pollora

**Version** : 1.0  
**Date** : 16 décembre 2025  
**Auteur** : Olivier / AmphiBee  
**Statut** : Draft

---

## 1. Contexte et Objectifs

### 1.1 Contexte

Le framework Pollora utilise actuellement des appels `error_log()` dispersés dans le code pour la journalisation des erreurs. Cette approche présente plusieurs limitations :

- Absence de niveaux de log structurés (debug, info, warning, error, critical)
- Pas de contextualisation des logs (métadonnées, stack traces)
- Impossibilité de router les logs vers différentes destinations
- Non-conformité avec les standards PSR-3
- Difficulté de debugging en environnement de développement

### 1.2 Objectifs

1. **Centraliser** la gestion des logs du framework Pollora
2. **Standardiser** l'interface de logging via PSR-3
3. **Intégrer** le système de logging Laravel avec un channel dédié `pollora`
4. **Maintenir** la compatibilité avec les contextes non-Laravel (fallback `error_log`)
5. **Respecter** l'architecture hexagonale (DDD) du framework

---

## 2. Architecture Proposée

### 2.1 Structure des Dossiers

```
src/Logging/
├── Domain/
│   ├── Contracts/
│   │   └── LoggerInterface.php          # Interface principale (extends PSR-3)
│   ├── Enums/
│   │   └── LogLevel.php                 # Énumération des niveaux de log
│   └── ValueObjects/
│       └── LogContext.php               # Objet valeur pour le contexte
├── Infrastructure/
│   ├── Providers/
│   │   └── LoggingServiceProvider.php   # Enregistrement des services
│   ├── Services/
│   │   ├── LaravelLogger.php            # Implémentation Laravel (channel pollora)
│   │   └── FallbackLogger.php           # Implémentation error_log standard
│   └── Factories/
│       └── LoggerFactory.php            # Factory pour résolution contextuelle
└── Application/
    └── Services/
        └── LoggingService.php           # Service applicatif (façade interne)
```

### 2.2 Diagramme de Dépendances

```
┌─────────────────────────────────────────────────────────────────┐
│                      Application Layer                           │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              LoggingService                              │    │
│  │         (Orchestration & Façade)                         │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       Domain Layer                               │
│  ┌──────────────────┐  ┌──────────────┐  ┌─────────────────┐   │
│  │ LoggerInterface  │  │   LogLevel   │  │   LogContext    │   │
│  │   (Contract)     │  │    (Enum)    │  │ (Value Object)  │   │
│  └──────────────────┘  └──────────────┘  └─────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              ▲
                              │
┌─────────────────────────────────────────────────────────────────┐
│                   Infrastructure Layer                           │
│  ┌─────────────────┐  ┌─────────────────┐  ┌────────────────┐  │
│  │  LaravelLogger  │  │ FallbackLogger  │  │ LoggerFactory  │  │
│  │ (channel:       │  │  (error_log)    │  │  (Résolution)  │  │
│  │   pollora)      │  │                 │  │                │  │
│  └─────────────────┘  └─────────────────┘  └────────────────┘  │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │           LoggingServiceProvider                         │    │
│  │        (Binding & Configuration)                         │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Spécifications Détaillées

### 3.1 Domain Layer

#### 3.1.1 LoggerInterface

**Fichier** : `src/Logging/Domain/Contracts/LoggerInterface.php`

```php
<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\Contracts;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Interface de logging pour le framework Pollora.
 * 
 * Étend PSR-3 avec des méthodes spécifiques au framework.
 */
interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Log avec préfixe automatique du module Pollora.
     *
     * @param string $level   Niveau de log PSR-3
     * @param string $message Message à logger
     * @param array  $context Contexte additionnel
     */
    public function logWithModule(string $level, string $message, array $context = []): void;

    /**
     * Retourne le nom du channel utilisé.
     */
    public function getChannelName(): string;

    /**
     * Vérifie si le logger est en mode debug.
     */
    public function isDebugEnabled(): bool;
}
```

**Justification** : L'extension de PSR-3 permet une compatibilité totale avec l'écosystème PHP tout en ajoutant des fonctionnalités spécifiques à Pollora.

#### 3.1.2 LogLevel Enum

**Fichier** : `src/Logging/Domain/Enums/LogLevel.php`

```php
<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\Enums;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Énumération des niveaux de log avec mapping PSR-3.
 */
enum LogLevel: string
{
    case EMERGENCY = PsrLogLevel::EMERGENCY;
    case ALERT     = PsrLogLevel::ALERT;
    case CRITICAL  = PsrLogLevel::CRITICAL;
    case ERROR     = PsrLogLevel::ERROR;
    case WARNING   = PsrLogLevel::WARNING;
    case NOTICE    = PsrLogLevel::NOTICE;
    case INFO      = PsrLogLevel::INFO;
    case DEBUG     = PsrLogLevel::DEBUG;

    /**
     * Retourne la priorité numérique (pour filtrage).
     */
    public function priority(): int
    {
        return match ($this) {
            self::EMERGENCY => 800,
            self::ALERT     => 700,
            self::CRITICAL  => 600,
            self::ERROR     => 500,
            self::WARNING   => 400,
            self::NOTICE    => 300,
            self::INFO      => 200,
            self::DEBUG     => 100,
        };
    }
}
```

#### 3.1.3 LogContext Value Object

**Fichier** : `src/Logging/Domain/ValueObjects/LogContext.php`

```php
<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\ValueObjects;

/**
 * Value Object encapsulant le contexte d'un log.
 */
final readonly class LogContext
{
    /**
     * @param string      $module     Module Pollora source (ex: "Hook", "PostType")
     * @param string|null $class      Classe source
     * @param string|null $method     Méthode source
     * @param array       $extra      Données additionnelles
     * @param \Throwable|null $exception Exception associée
     */
    public function __construct(
        public string $module,
        public ?string $class = null,
        public ?string $method = null,
        public array $extra = [],
        public ?\Throwable $exception = null,
    ) {}

    /**
     * Convertit en tableau pour le contexte PSR-3.
     */
    public function toArray(): array
    {
        $context = [
            'pollora_module' => $this->module,
        ];

        if ($this->class !== null) {
            $context['class'] = $this->class;
        }

        if ($this->method !== null) {
            $context['method'] = $this->method;
        }

        if ($this->exception !== null) {
            $context['exception'] = $this->exception;
        }

        return array_merge($context, $this->extra);
    }

    /**
     * Factory method pour création depuis une classe.
     */
    public static function fromClass(string $className, ?string $method = null, array $extra = []): self
    {
        $parts = explode('\\', $className);
        
        // Détection du module depuis le namespace (ex: Pollora\Hook\...)
        $module = 'Core';
        if (count($parts) >= 2 && $parts[0] === 'Pollora') {
            $module = $parts[1];
        }

        return new self(
            module: $module,
            class: $className,
            method: $method,
            extra: $extra,
        );
    }
}
```

---

### 3.2 Infrastructure Layer

#### 3.2.1 LaravelLogger

**Fichier** : `src/Logging/Infrastructure/Services/LaravelLogger.php`

```php
<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Services;

use Illuminate\Log\LogManager;
use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * Implémentation du logger utilisant le système Laravel.
 * 
 * Utilise le channel "pollora" configuré dans config/logging.php
 */
final class LaravelLogger implements LoggerInterface
{
    use LoggerTrait;

    private const CHANNEL_NAME = 'pollora';

    public function __construct(
        private readonly LogManager $logManager,
        private readonly bool $debugEnabled = false,
    ) {}

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logManager
            ->channel(self::CHANNEL_NAME)
            ->log($level, $this->formatMessage($message), $this->enrichContext($context));
    }

    public function logWithModule(string $level, string $message, array $context = []): void
    {
        $context['pollora_framework'] = true;
        $this->log($level, $message, $context);
    }

    public function getChannelName(): string
    {
        return self::CHANNEL_NAME;
    }

    public function isDebugEnabled(): bool
    {
        return $this->debugEnabled;
    }

    private function formatMessage(string|Stringable $message): string
    {
        return '[Pollora] ' . (string) $message;
    }

    private function enrichContext(array $context): array
    {
        return array_merge([
            'framework' => 'pollora',
            'timestamp' => now()->toIso8601String(),
        ], $context);
    }
}
```

#### 3.2.2 FallbackLogger

**Fichier** : `src/Logging/Infrastructure/Services/FallbackLogger.php`

```php
<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Services;

use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * Implémentation fallback utilisant error_log natif PHP.
 * 
 * Utilisé quand le container Laravel n'est pas disponible.
 */
final class FallbackLogger implements LoggerInterface
{
    use LoggerTrait;

    private const CHANNEL_NAME = 'error_log';

    public function __construct(
        private readonly bool $debugEnabled = false,
    ) {}

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $formattedMessage = $this->format($level, $message, $context);
        error_log($formattedMessage);
    }

    public function logWithModule(string $level, string $message, array $context = []): void
    {
        $context['pollora_framework'] = true;
        $this->log($level, $message, $context);
    }

    public function getChannelName(): string
    {
        return self::CHANNEL_NAME;
    }

    public function isDebugEnabled(): bool
    {
        return $this->debugEnabled;
    }

    private function format(string $level, string|Stringable $message, array $context): string
    {
        $levelUpper = strtoupper($level);
        $contextString = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        
        return sprintf('[Pollora] [%s] %s%s', $levelUpper, $message, $contextString);
    }
}
```

#### 3.2.3 LoggerFactory

**Fichier** : `src/Logging/Infrastructure/Factories/LoggerFactory.php`

```php
<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Factories;

use Illuminate\Contracts\Container\Container;
use Illuminate\Log\LogManager;
use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Pollora\Logging\Infrastructure\Services\FallbackLogger;
use Pollora\Logging\Infrastructure\Services\LaravelLogger;

/**
 * Factory pour la création contextuelle du logger approprié.
 */
final class LoggerFactory
{
    public function __construct(
        private readonly ?Container $container = null,
    ) {}

    /**
     * Crée l'instance de logger appropriée selon le contexte.
     */
    public function create(): LoggerInterface
    {
        // Si le container Laravel est disponible avec le LogManager
        if ($this->isLaravelAvailable()) {
            return new LaravelLogger(
                logManager: $this->container->make(LogManager::class),
                debugEnabled: $this->isDebugEnabled(),
            );
        }

        // Fallback vers error_log standard
        return new FallbackLogger(
            debugEnabled: $this->isDebugEnabled(),
        );
    }

    private function isLaravelAvailable(): bool
    {
        if ($this->container === null) {
            return false;
        }

        return $this->container->bound(LogManager::class);
    }

    private function isDebugEnabled(): bool
    {
        if ($this->container === null) {
            return false;
        }

        return (bool) $this->container->make('config')->get('app.debug', false);
    }
}
```

#### 3.2.4 LoggingServiceProvider

**Fichier** : `src/Logging/Infrastructure/Providers/LoggingServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Pollora\Logging\Infrastructure\Factories\LoggerFactory;

/**
 * Service Provider pour l'enregistrement du système de logging Pollora.
 */
final class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerFactory();
        $this->registerLogger();
        $this->registerLoggingService();
    }

    public function boot(): void
    {
        $this->mergeLoggingConfig();
    }

    private function registerFactory(): void
    {
        $this->app->singleton(LoggerFactory::class, fn ($app) => new LoggerFactory($app));
    }

    private function registerLogger(): void
    {
        $this->app->singleton(
            LoggerInterface::class,
            fn ($app) => $app->make(LoggerFactory::class)->create()
        );
    }

    private function registerLoggingService(): void
    {
        $this->app->singleton(LoggingService::class);
    }

    /**
     * Fusionne la configuration du channel Pollora.
     */
    private function mergeLoggingConfig(): void
    {
        $config = $this->app->make('config');
        
        $polloraChannel = [
            'driver' => 'daily',
            'path' => storage_path('logs/pollora.log'),
            'level' => env('POLLORA_LOG_LEVEL', 'debug'),
            'days' => env('POLLORA_LOG_DAYS', 14),
            'replace_placeholders' => true,
        ];

        $channels = $config->get('logging.channels', []);
        $channels['pollora'] = $polloraChannel;
        
        $config->set('logging.channels', $channels);
    }
}
```

---

### 3.3 Application Layer

#### 3.3.1 LoggingService

**Fichier** : `src/Logging/Application/Services/LoggingService.php`

Le `LoggingService` fournit une API simplifiée pour le logging dans le framework avec des méthodes génériques pour tous les niveaux de log PSR-3.

**Implémentation actuelle** :
- **Méthodes génériques** : `emergency()`, `alert()`, `critical()`, `error()`, `warning()`, `notice()`, `info()`, `debug()`
- **Support des exceptions** : Toutes les méthodes critiques supportent un paramètre `?\Throwable $exception`
- **Contexte structuré** : Utilise `LogContext` pour la contextualisation
- **Debug conditionnel** : La méthode `debug()` vérifie automatiquement si le debug est activé

**Signature des méthodes** :
```php
// Méthodes critiques (emergency, alert, critical, error)
public function error(string $message, ?LogContext $context = null, ?Throwable $exception = null): void

// Méthodes d'information (warning, notice, info)  
public function warning(string $message, ?LogContext $context = null): void

// Méthode de debug (avec vérification automatique du niveau)
public function debug(string $message, ?LogContext $context = null): void
```

**Utilisation recommandée** :
```php
// Error avec exception et contexte complet
$context = new LogContext(
    module: 'Hook',
    class: $className,
    method: $methodName,
    extra: ['hook_type' => $hookType]
);
$this->loggingService->error("Failed to register {$hookType} hook", $context, $exception);

// Warning simple avec contexte
$context = LogContext::fromClass($className, $methodName);
$this->loggingService->warning("Configuration may be incomplete", $context);

// Info sans contexte
$this->loggingService->info("Module initialized successfully");

// Debug (automatiquement ignoré si debug désactivé)
$this->loggingService->debug("Processing discovery items", $context);
```

**Méthodes utilitaires** :
- `getLogger(): LoggerInterface` - Accès au logger sous-jacent
- `isDebugEnabled(): bool` - Vérification du mode debug
- `getChannelName(): string` - Nom du channel utilisé

---

## 4. Intégration par Injection de Dépendance

### 4.1 Principe d'Intégration

Le `LoggingService` s'intègre dans les classes Pollora via l'injection de dépendance Laravel. Le service provider `LoggingServiceProvider` enregistre le service comme singleton dans le container DI.

### 4.2 Exemple d'Intégration dans une Classe Discovery

**Pattern recommandé** pour les classes de discovery existantes :

```php
<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Pollora\Hook\Domain\Contracts\Action as ActionContract;
use Pollora\Hook\Domain\Contracts\Filter as FilterContract;
use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\ValueObjects\LogContext;

final class HookDiscovery implements DiscoveryInterface
{
    use HasInstancePool, IsDiscovery;

    /**
     * Injection du LoggingService via le constructeur.
     */
    public function __construct(
        private readonly ActionContract $actionService,
        private readonly FilterContract $filterService,
        private readonly LoggingService $loggingService, // <- Injection DI
    ) {}

    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            try {
                $this->processItem($discoveredItem);
            } catch (\Throwable $e) {
                // Utilisation du service injecté avec contexte structuré
                $context = new LogContext(
                    module: 'Hook',
                    class: $discoveredItem['class'],
                    method: $discoveredItem['method'],
                    extra: ['hook_type' => $discoveredItem['type']]
                );
                
                $this->loggingService->error(
                    "Failed to register {$discoveredItem['type']} hook",
                    $context,
                    $e
                );
            }
        }
    }
}
```

### 4.3 Intégration dans les Classes sans DI Existante

**Scénarios d'ajout de l'injection de dépendance** :

1. **Classes avec constructeur existant** : Ajouter le paramètre `LoggingService`
2. **Classes sans constructeur** : Créer un constructeur avec injection
3. **Classes statiques** : Refactoriser vers une approche orientée objet ou utiliser un service locator (non recommandé)

**Exemple - Classe sans DI existante** :

```php
// AVANT (sans DI)
final class SomeService
{
    public function process(): void
    {
        try {
            // ... logique métier
        } catch (\Throwable $e) {
            error_log("Error in SomeService: " . $e->getMessage());
        }
    }
}

// APRÈS (avec DI)
final class SomeService
{
    public function __construct(
        private readonly LoggingService $loggingService,
    ) {}
    
    public function process(): void
    {
        try {
            // ... logique métier
        } catch (\Throwable $e) {
            $context = LogContext::fromClass(self::class, 'process');
            $this->loggingService->error("Error processing data", $context, $e);
        }
    }
}
```

### 4.4 Enregistrement dans le Service Provider

**Classes qui nécessitent l'injection** doivent être enregistrées dans un service provider :

```php
// Dans PolloraServiceProvider ou module-specific provider
public function register(): void
{
    $this->app->singleton(SomeService::class);
    
    // Le LoggingService sera automatiquement injecté
    // car il est enregistré comme singleton
}
```

### 4.5 Compatibilité avec les Classes Legacy

**Pour les classes qui ne peuvent pas être refactorisées immédiatement** :

```php
use Pollora\Logging\Application\Services\LoggingService;

final class LegacyClass
{
    private ?LoggingService $logger = null;
    
    /**
     * Lazy loading du LoggingService via le container global.
     */
    private function getLogger(): LoggingService
    {
        if ($this->logger === null) {
            $this->logger = app(LoggingService::class);
        }
        
        return $this->logger;
    }
    
    public function someMethod(): void
    {
        try {
            // ... code legacy
        } catch (\Throwable $e) {
            $context = LogContext::fromClass(self::class, 'someMethod');
            $this->getLogger()->error("Legacy method failed", $context, $e);
        }
    }
}
```

**Note** : Cette approche est temporaire et doit être migrée vers l'injection de dépendance propre.

### 4.6 Tests avec LoggingService

**Injection de mocks dans les tests** :

```php
use PHPUnit\Framework\TestCase;
use Pollora\Logging\Application\Services\LoggingService;

final class SomeServiceTest extends TestCase
{
    public function testProcessWithLogging(): void
    {
        // Arrangement
        $mockLogger = $this->createMock(LoggingService::class);
        $service = new SomeService($mockLogger);
        
        // Expectation
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error processing'),
                $this->isInstanceOf(LogContext::class),
                $this->isInstanceOf(\Throwable::class)
            );
            
        // Action & Assertion
        $service->process(); // Method that triggers error
    }
}
```

---

## 5. Configuration

### 4.1 Variables d'Environnement

| Variable | Description | Valeur par défaut |
|----------|-------------|-------------------|
| `POLLORA_LOG_LEVEL` | Niveau minimum de log | `debug` |
| `POLLORA_LOG_DAYS` | Rétention des fichiers (jours) | `14` |

### 4.2 Configuration Laravel (optionnelle)

Si une configuration avancée est nécessaire, créer `config/pollora-logging.php` :

```php
<?php

return [
    'channel' => env('POLLORA_LOG_CHANNEL', 'pollora'),
    
    'level' => env('POLLORA_LOG_LEVEL', 'debug'),
    
    'days' => env('POLLORA_LOG_DAYS', 14),
    
    // Activer le logging des performances (discovery, hooks)
    'performance' => env('POLLORA_LOG_PERFORMANCE', false),
    
    // Modules à exclure du logging
    'excluded_modules' => [],
];
```

---

## 5. Migration du Code Existant

### 5.1 Pattern de Remplacement

**Avant** (appel direct `error_log`) :

```php
try {
    // ... code
} catch (\Throwable $e) {
    error_log("Failed to register {$hookType} hook from method {$className}::{$methodName}: " . $e->getMessage());
}
```

**Après** (injection du LoggingService avec approche générique) :

```php
public function __construct(
    private readonly LoggingService $loggingService,
) {}

// Dans la méthode :
try {
    // ... code
} catch (\Throwable $e) {
    $context = new LogContext(
        module: 'Hook',
        class: $className,
        method: $methodName,
        extra: ['hook_type' => $hookType]
    );
    $this->loggingService->error("Failed to register {$hookType} hook", $context, $e);
}
```

### 5.2 Classes à Migrer

#### 5.2.1 Classes déjà intégrées ✅

Ces classes utilisent déjà le LoggingService avec injection de dépendance :

| Fichier | Module | Statut |
|---------|--------|--------|
| `src/Hook/Infrastructure/Services/HookDiscovery.php` | Hook | ✅ Déjà intégré |
| `src/Discovery/Infrastructure/Services/DiscoveryEngine.php` | Discovery | ✅ Déjà intégré |
| `src/Discovery/Infrastructure/Services/ServiceProviderDiscovery.php` | Discovery | ✅ Déjà intégré |
| `src/Modules/Infrastructure/Services/ModuleDiscoveryOrchestrator.php` | Modules | ✅ Déjà intégré |

#### 5.2.2 Classes prioritaires à migrer 🔴

**Priorité HAUTE** (Facile à implémenter) :

| Fichier | Module | Faisabilité | Impact |
|---------|--------|-------------|--------|
| `src/Theme/Application/Services/ThemeRegistrar.php` | Theme | Facile | Service critique |
| `src/Plugin/Application/Services/PluginRegistrar.php` | Plugin | Facile | Service critique |
| `src/Theme/Infrastructure/Repositories/ThemeRepository.php` | Theme | Facile | Repository central |
| `src/Theme/Infrastructure/Providers/ThemeComponentProvider.php` | Theme | Facile | Gestion composants |

**Priorité MOYENNE** (Modérément facile) :

| Fichier | Module | Faisabilité | Impact |
|---------|--------|-------------|--------|
| `src/PostType/Infrastructure/Services/PostTypeDiscovery.php` | PostType | Moyen | Discovery service |
| `src/Taxonomy/Infrastructure/Services/TaxonomyDiscovery.php` | Taxonomy | Moyen | Discovery service |
| `src/Schedule/Infrastructure/Services/ScheduleDiscovery.php` | Schedule | Moyen | Discovery service |
| `src/Exceptions/Infrastructure/Handlers/ModuleAwareExceptionHandler.php` | Exceptions | Moyen | Exception handler |

#### 5.2.3 Plan de Migration

**Phase 1 - Quick wins (1-2 jours)**
- `ThemeRegistrar` : 7 occurrences `error_log()`
- `PluginRegistrar` : 6 occurrences `error_log()`
- `ThemeRepository` : 1 occurrence `error_log()`
- `ThemeComponentProvider` : 1 occurrence `error_log()`

**Phase 2 - Discovery services (2-3 jours)**
- `PostTypeDiscovery` : 5 occurrences `error_log()`
- `TaxonomyDiscovery` : 5 occurrences `error_log()`
- `ScheduleDiscovery` : 2 occurrences `error_log()`

**Phase 3 - Services complexes (1-2 jours)**
- `ModuleAwareExceptionHandler` : 2 occurrences `error_log()`

#### 5.2.4 Pattern de Migration Standard

```php
// AVANT
final class SomeService
{
    public function __construct(
        private readonly SomeDependency $dependency
    ) {}
    
    public function process(): void
    {
        try {
            // ... logique
        } catch (\Throwable $e) {
            error_log("Error in SomeService: " . $e->getMessage());
        }
    }
}

// APRÈS
final class SomeService
{
    public function __construct(
        private readonly SomeDependency $dependency,
        private readonly LoggingService $loggingService // <- Ajout injection DI
    ) {}
    
    public function process(): void
    {
        try {
            // ... logique
        } catch (\Throwable $e) {
            $context = LogContext::fromClass(self::class, 'process');
            $this->loggingService->error("Error processing data", $context, $e);
        }
    }
}
```

### 5.3 Exemple Concret : HookDiscovery Refactorisé (Implémentation Actuelle)

Le `HookDiscovery` utilise déjà l'injection de dépendance avec le `LoggingService` et l'approche générique :

```php
<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\ValueObjects\LogContext;
// ... autres imports

final class HookDiscovery implements DiscoveryInterface
{
    use HasInstancePool, IsDiscovery;

    public function __construct(
        private readonly ActionContract $actionService,
        private readonly FilterContract $filterService,
        private readonly LoggingService $loggingService
    ) {}

    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            [
                'type' => $hookType,
                'class' => $className,
                'method' => $methodName,
                'attribute' => $hookAttribute,
                'reflection_method' => $reflectionMethod
            ] = $discoveredItem;

            try {
                // Registration logic here...
                if ($hookType === 'action') {
                    $action = $hookAttribute->newInstance();
                    $instance = $this->getInstanceFromPool($className);
                    $this->actionService->add(
                        hooks: $action->hook,
                        callback: [$instance, $methodName],
                        priority: $action->priority
                    );
                } elseif ($hookType === 'filter') {
                    $filter = $hookAttribute->newInstance();
                    $instance = $this->getInstanceFromPool($className);
                    $this->filterService->add(
                        hooks: $filter->hook,
                        callback: [$instance, $methodName],
                        priority: $filter->priority
                    );
                }
            } catch (\Throwable $e) {
                // Utilisation de l'approche générique avec LogContext
                $context = new LogContext(
                    module: 'Hook',
                    class: $className,
                    method: $methodName,
                    extra: ['hook_type' => $hookType]
                );
                $this->loggingService->error("Failed to register {$hookType} hook", $context, $e);
            }
        }
    }

    // ... autres méthodes
}
```

---

## 6. Tests

### 6.1 Structure des Tests

```
tests/Unit/Logging/
├── Domain/
│   ├── Enums/
│   │   └── LogLevelTest.php
│   └── ValueObjects/
│       └── LogContextTest.php
├── Infrastructure/
│   ├── Services/
│   │   ├── LaravelLoggerTest.php
│   │   └── FallbackLoggerTest.php
│   └── Factories/
│       └── LoggerFactoryTest.php
└── Application/
    └── Services/
        └── LoggingServiceTest.php
```

### 6.2 Exemple de Test : LogContextTest

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Logging\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Pollora\Logging\Domain\ValueObjects\LogContext;

#[CoversClass(LogContext::class)]
final class LogContextTest extends TestCase
{
    #[Test]
    public function it_creates_context_with_all_properties(): void
    {
        $exception = new \RuntimeException('Test error');
        
        $context = new LogContext(
            module: 'Hook',
            class: 'TestClass',
            method: 'testMethod',
            extra: ['key' => 'value'],
            exception: $exception,
        );

        $array = $context->toArray();

        $this->assertSame('Hook', $array['pollora_module']);
        $this->assertSame('TestClass', $array['class']);
        $this->assertSame('testMethod', $array['method']);
        $this->assertSame('value', $array['key']);
        $this->assertSame($exception, $array['exception']);
    }

    #[Test]
    public function it_creates_context_from_class_name(): void
    {
        $context = LogContext::fromClass(
            className: 'Pollora\\Hook\\Infrastructure\\Services\\HookDiscovery',
            method: 'apply'
        );

        $this->assertSame('Hook', $context->module);
        $this->assertSame('apply', $context->method);
    }

    #[Test]
    public function it_defaults_to_core_module_for_unknown_namespace(): void
    {
        $context = LogContext::fromClass('SomeClass');

        $this->assertSame('Core', $context->module);
    }
}
```

### 6.3 Exemple de Test : LoggingServiceTest

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Logging\Application\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\Contracts\LoggerInterface;

#[CoversClass(LoggingService::class)]
final class LoggingServiceTest extends TestCase
{
    #[Test]
    public function it_logs_hook_error_with_correct_context(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        
        $logger->expects($this->once())
            ->method('logWithModule')
            ->with(
                'error',
                $this->stringContains('Failed to register action hook'),
                $this->callback(function (array $context) {
                    return $context['pollora_module'] === 'Hook'
                        && $context['hook_type'] === 'action'
                        && $context['class'] === 'TestClass'
                        && $context['method'] === 'testMethod';
                })
            );

        $service = new LoggingService($logger);
        
        $service->hookError(
            hookType: 'action',
            className: 'TestClass',
            methodName: 'testMethod',
            exception: new \RuntimeException('Test')
        );
    }

    #[Test]
    public function it_skips_debug_when_not_enabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('isDebugEnabled')->willReturn(false);
        $logger->expects($this->never())->method('logWithModule');

        $service = new LoggingService($logger);
        $service->debug('This should not be logged');
    }
}
```

---

## 7. Plan d'Implémentation

### Phase 1 : Foundation (Priorité Haute)

1. Créer la structure de dossiers
2. Implémenter `LoggerInterface` et `LogLevel`
3. Implémenter `LogContext`
4. Implémenter `FallbackLogger`
5. Écrire les tests unitaires associés

### Phase 2 : Intégration Laravel (Priorité Haute)

1. Implémenter `LaravelLogger`
2. Implémenter `LoggerFactory`
3. Créer `LoggingServiceProvider`
4. Intégrer dans `PolloraServiceProvider`
5. Tests d'intégration

### Phase 3 : Service Applicatif (Priorité Moyenne)

1. Implémenter `LoggingService`
2. Ajouter méthodes spécialisées (hookError, discoveryError, etc.)
3. Tests unitaires complets

### Phase 4 : Migration (Priorité Moyenne)

1. Auditer tous les appels `error_log` existants
2. Migrer `HookDiscovery`
3. Migrer autres classes identifiées
4. Valider la couverture de tests

### Phase 5 : Documentation (Priorité Basse)

1. Documenter l'API dans le README
2. Ajouter des exemples d'utilisation
3. Mettre à jour CLAUDE.md

---

## 8. Critères d'Acceptation

- [ ] Couverture de tests à 100% sur les nouvelles classes
- [ ] Analyse PHPStan niveau 5 sans erreur
- [ ] Aucun appel `error_log` direct dans le code framework (hors FallbackLogger)
- [ ] Channel `pollora` fonctionnel dans Laravel
- [ ] Fallback `error_log` opérationnel sans Laravel
- [ ] Documentation à jour

---

## 9. Références

- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [Laravel Logging Documentation](https://laravel.com/docs/logging)
- [Architecture Hexagonale](https://alistair.cockburn.us/hexagonal-architecture/)
