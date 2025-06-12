<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Routing\Router;
use Illuminate\Translation\Translator;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;

/**
 * Generic module bootstrap service inspired by nwidart/laravel-modules.
 *
 * This service handles the registration and booting of modules in a Laravel application.
 * It provides functionality for:
 * - Module registration and autoloading
 * - Service provider discovery and loading
 * - Migration path registration
 * - Translation namespace registration
 * - Route registration with different types (web, api, console)
 */
class ModuleBootstrap
{
    /**
     * Create a new module bootstrap instance.
     *
     * @param  Container  $app  The Laravel application container
     * @param  ModuleRepositoryInterface  $repository  The module repository for accessing modules
     * @param  Router  $router  The Laravel router for registering routes
     */
    public function __construct(
        protected Container $app,
        protected ModuleRepositoryInterface $repository,
        protected Router $router
    ) {}

    /**
     * Register all enabled modules in the application.
     *
     * This method performs the following operations:
     * 1. Registers autoloading for each enabled module
     * 2. Discovers and loads service providers using Laravel's ProviderRepository
     * 3. Registers additional module files
     *
     * The order is important: autoloading must be registered first before
     * attempting to discover service providers.
     */
    public function registerModules(): void
    {
        $this->registerModuleAutoloading();
        $this->loadModuleServiceProviders();
        $this->registerModuleFiles();
    }

    /**
     * Boot all enabled modules.
     *
     * Calls the boot method on each enabled module that implements it.
     * This is typically called after all modules have been registered.
     */
    public function bootModules(): void
    {
        foreach ($this->repository->allEnabled() as $module) {
            if (! method_exists($module, 'boot')) {
                continue;
            }

            $module->boot();
        }
    }

    /**
     * Register migration paths for all enabled modules.
     *
     * This method registers migration paths with Laravel's Migrator service
     * so that module migrations can be discovered and run alongside
     * application migrations.
     */
    public function registerMigrations(): void
    {
        if (! $this->isAutoDiscoveryEnabled('migrations')) {
            return;
        }

        $this->app->resolving(Migrator::class, function (Migrator $migrator): void {
            $this->registerMigrationPathsWithMigrator($migrator);
        });
    }

    /**
     * Register translation namespaces for all enabled modules.
     *
     * This method adds translation namespaces to Laravel's Translator service
     * so that module translations can be loaded and accessed using the
     * module's namespace.
     */
    public function registerTranslations(): void
    {
        if (! $this->isAutoDiscoveryEnabled('translations')) {
            return;
        }

        $this->app->afterResolving('translator', function ($translator): void {
            if (! $translator instanceof Translator) {
                return;
            }

            $this->registerTranslationNamespacesWithTranslator($translator);
        });
    }

    /**
     * Register routes for all enabled modules.
     *
     * This method discovers and registers different types of routes:
     * - Web routes (with web middleware)
     * - API routes (with api middleware, api prefix, and api. name prefix)
     * - Console routes (without middleware or prefixes)
     */
    public function registerRoutes(): void
    {
        if (! $this->isAutoDiscoveryEnabled('routes')) {
            return;
        }

        foreach ($this->repository->allEnabled() as $module) {
            if (! method_exists($module, 'getRoutesPath')) {
                continue;
            }

            $routePath = $module->getRoutesPath();
            if (! is_dir($routePath)) {
                continue;
            }

            $this->registerModuleRoutes($routePath);
        }
    }

    /**
     * Register autoloading for all enabled modules.
     *
     * This must happen before service provider discovery to ensure
     * all module classes can be properly loaded.
     */
    private function registerModuleAutoloading(): void
    {
        foreach ($this->repository->allEnabled() as $module) {
            if (! method_exists($module, 'register')) {
                continue;
            }

            $module->register();
        }
    }

    /**
     * Load service providers for all modules using Laravel's ProviderRepository.
     */
    private function loadModuleServiceProviders(): void
    {
        $manifest = $this->app->make(ModuleManifest::class);

        $providerRepository = new ProviderRepository(
            $this->app,
            new Filesystem,
            $this->getCachedModulePath()
        );

        $providerRepository->load($manifest->getProviders());
    }

    /**
     * Register additional module files discovered by the manifest.
     */
    private function registerModuleFiles(): void
    {
        $manifest = $this->app->make(ModuleManifest::class);
        $manifest->registerFiles();
    }

    /**
     * Register migration paths with the given migrator instance.
     *
     * @param  Migrator  $migrator  The Laravel migrator instance
     */
    private function registerMigrationPathsWithMigrator(Migrator $migrator): void
    {
        foreach ($this->repository->allEnabled() as $module) {
            if (! method_exists($module, 'getMigrationsPath')) {
                continue;
            }

            $migrationsPath = $module->getMigrationsPath();
            if (! is_dir($migrationsPath)) {
                continue;
            }

            $migrator->path($migrationsPath);
        }
    }

    /**
     * Register translation namespaces with the given translator instance.
     *
     * @param  Translator  $translator  The Laravel translator instance
     */
    private function registerTranslationNamespacesWithTranslator(Translator $translator): void
    {
        foreach ($this->repository->allEnabled() as $module) {
            $langPath = $module->getPath().'/languages';
            if (! is_dir($langPath)) {
                continue;
            }

            $translator->addNamespace($module->getLowerName(), $langPath);
            $translator->addJsonPath($langPath);
        }
    }

    /**
     * Register all route types for a specific module.
     *
     * @param  string  $routePath  The path to the module's routes directory
     */
    private function registerModuleRoutes(string $routePath): void
    {
        $routeConfigurations = $this->getRouteConfigurations();

        foreach ($routeConfigurations as $routeType => $config) {
            $this->registerRouteType($routePath, $routeType, $config);
        }
    }

    /**
     * Get the configuration for different route types.
     *
     * @return array<string, array<string, mixed>> Route type configurations
     */
    private function getRouteConfigurations(): array
    {
        return [
            'web' => [
                'middleware' => 'web',
            ],
            'api' => [
                'middleware' => 'api',
                'prefix' => 'api',
                'name' => 'api.',
            ],
            'console' => [],
        ];
    }

    /**
     * Register a specific type of route for a module.
     *
     * @param  string  $routePath  The path to the module's routes directory
     * @param  string  $routeType  The type of route (web, api, console, etc.)
     * @param  array<string, mixed>  $config  The route configuration (middleware, prefix, etc.)
     */
    private function registerRouteType(string $routePath, string $routeType, array $config = []): void
    {
        $routeFile = $this->getRouteFilePath($routePath, $routeType);
        if (! file_exists($routeFile)) {
            return;
        }

        $this->router->group($config, function () use ($routeFile): void {
            require $routeFile;
        });
    }

    /**
     * Get the full path to a specific route file.
     *
     * @param  string  $routePath  The base routes directory path
     * @param  string  $routeType  The route type (file name without extension)
     * @return string The full path to the route file
     */
    private function getRouteFilePath(string $routePath, string $routeType): string
    {
        return $routePath.'/'.$routeType.'.php';
    }

    /**
     * Check if auto-discovery is enabled for a specific feature.
     *
     * @param  string  $feature  The feature name (migrations, translations, routes, etc.)
     * @return bool True if auto-discovery is enabled for the feature
     */
    private function isAutoDiscoveryEnabled(string $feature): bool
    {
        return $this->app['config']->get("modules.auto-discover.{$feature}", true);
    }

    /**
     * Get the cached module path for service provider caching.
     *
     * This path is used by Laravel's ProviderRepository to cache
     * discovered service providers for better performance.
     *
     * @return string The cached module manifest path
     */
    protected function getCachedModulePath(): string
    {
        return str_replace('services.php', 'modules.php', $this->app->getCachedServicesPath());
    }
}
