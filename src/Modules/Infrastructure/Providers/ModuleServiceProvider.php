<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Domain\Contracts\OnDemandDiscoveryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Modules\Infrastructure\Services\ModuleBootstrap;
use Pollora\Modules\Infrastructure\Services\ModuleManifest;
use Pollora\Modules\Infrastructure\Services\OnDemandDiscoveryService;

/**
 * Main service provider for the generic module system.
 *
 * This provider follows the nwidart/laravel-modules pattern but adapted for our architecture.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register ModuleAutoloader service
        $this->app->singleton(ModuleAutoloader::class, function ($app) {
            return new ModuleAutoloader($app);
        });

        // Register OnDemandDiscoveryService
        $this->app->singleton(OnDemandDiscoveryService::class, function ($app) {
            return new OnDemandDiscoveryService($app);
        });

        // Register interface binding
        $this->app->bind(OnDemandDiscoveryInterface::class, OnDemandDiscoveryService::class);

        // Register alias for easier access
        $this->app->alias(OnDemandDiscoveryService::class, 'modules.discovery');

        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/modules.php', 'modules');
    }

    public function boot(Router $router): void
    {
        // Load helper functions
        $this->loadHelperFunctions();

        // Register ModuleManifest service
        $this->app->singleton(ModuleManifest::class, function ($app) {
            return new ModuleManifest(
                new Filesystem,
                $this->getModulePaths(),
                $this->getCachedModulePath(),
                $app->make(ModuleRepositoryInterface::class),
                null // No longer using legacy scout
            );
        });

        // Register ModuleBootstrap service
        $this->app->singleton(ModuleBootstrap::class, function ($app) use ($router) {
            return new ModuleBootstrap(
                $app,
                $app->make(ModuleRepositoryInterface::class),
                $router
            );
        });

        // Register and boot modules if a repository is available
        if ($this->app->bound(ModuleRepositoryInterface::class)) {
            $bootstrap = $this->app->make(ModuleBootstrap::class);

            // Register modules
            $bootstrap->registerModules();

            // Boot modules on next cycle
            $this->app->booted(function () use ($bootstrap) {

                $bootstrap->bootModules();
                // Register migrations and translations
                $bootstrap->registerMigrations();
                $bootstrap->registerTranslations();

                // Register module routes
                $bootstrap->registerRoutes();

                // Fire event to notify that module routes have been registered
                // This allows other services (like RouteServiceProvider) to register
                // their fallback routes after all module routes are in place
                Event::dispatch('modules.routes.registered');
            });
        }
    }

    /**
     * Load helper functions.
     */
    protected function loadHelperFunctions(): void
    {
        require_once __DIR__.'/../../UI/Helpers/discovery_functions.php';
    }

    /**
     * Get module paths from configuration.
     */
    protected function getModulePaths(): array
    {
        return [$this->app['config']->get('modules.paths.modules', base_path('modules'))];
    }

    /**
     * Get the cached module path.
     */
    protected function getCachedModulePath(): string
    {
        return Str::replaceLast('services.php', 'modules.php', $this->app->getCachedServicesPath());
    }
}
