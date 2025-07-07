<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Modules\Infrastructure\Services\ModuleBootstrap;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Pollora\Modules\Infrastructure\Services\ModuleManifest;

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

        // Register ModuleDiscoveryOrchestrator
        $this->app->singleton(ModuleDiscoveryOrchestrator::class, function ($app) {
            return new ModuleDiscoveryOrchestrator($app);
        });

        // Register interface binding
        $this->app->bind(ModuleDiscoveryOrchestratorInterface::class, ModuleDiscoveryOrchestrator::class);

        // Register alias for easier access
        $this->app->alias(ModuleDiscoveryOrchestrator::class, 'modules.discovery');

        // Register new generic module services
        $this->app->singleton(\Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader::class, function ($app) {
            return new \Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader(
                $app,
                $app->make(\Pollora\Config\Domain\Contracts\ConfigRepositoryInterface::class)
            );
        });

        $this->app->singleton(\Pollora\Modules\Infrastructure\Services\ModuleComponentManager::class, function ($app) {
            return new \Pollora\Modules\Infrastructure\Services\ModuleComponentManager($app);
        });

        $this->app->singleton(\Pollora\Modules\Infrastructure\Services\ModuleAssetManager::class, function ($app) {
            return new \Pollora\Modules\Infrastructure\Services\ModuleAssetManager($app);
        });

        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/modules.php', 'modules');
    }

    public function boot(Router $router): void
    {
        // Load helper functions
        $this->loadHelperFunctions();

        // Legacy services kept for compatibility but simplified
        $this->app->singleton(ModuleManifest::class, function ($app) {
            return new ModuleManifest(
                new Filesystem,
                $this->getModulePaths(),
                $this->getCachedModulePath(),
                $app->make(ModuleRepositoryInterface::class),
                null // No longer using legacy scout
            );
        });

        $this->app->singleton(ModuleBootstrap::class, function ($app) use ($router) {
            return new ModuleBootstrap(
                $app,
                $app->make(ModuleRepositoryInterface::class),
                $router
            );
        });

        // Setup Laravel Module discovery only
        $this->app->booted(function () {
            // Discover Laravel modules but don't apply yet
            $this->discoverLaravelModules();

            // Fire event to notify that modules are ready
            Event::dispatch('modules.routes.registered');

            // Schedule application after WordPress is ready
            $this->scheduleWordPressReadyApplication();
        });
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

    /**
     * Schedule Laravel module application to happen after WordPress is loaded.
     */
    protected function scheduleWordPressReadyApplication(): void
    {
        // Use WordPress 'wp_loaded' hook to ensure WordPress functions are available
        // This hook fires after WordPress is fully loaded but before any headers are sent
        add_action('wp_loaded', function () {
            $this->applyLaravelModules();
        });
    }

    /**
     * Discover Laravel modules using nwidart/laravel-modules (discovery only, no apply).
     */
    protected function discoverLaravelModules(): void
    {
        if (! $this->app->bound(ModuleDiscoveryOrchestratorInterface::class)) {
            return;
        }

        try {
            /** @var ModuleDiscoveryOrchestratorInterface $orchestrator */
            $orchestrator = $this->app->make(ModuleDiscoveryOrchestratorInterface::class);

            // Check if orchestrator has Laravel module discovery capability
            if (method_exists($orchestrator, 'discoverLaravelModules')) {
                $orchestrator->discoverLaravelModules();
            }
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Laravel Module discovery error in ModuleServiceProvider: '.$e->getMessage());
            }
        }
    }

    /**
     * Apply discovered Laravel modules.
     */
    protected function applyLaravelModules(): void
    {
        if (! $this->app->bound(ModuleDiscoveryOrchestratorInterface::class)) {
            return;
        }

        try {
            /** @var ModuleDiscoveryOrchestratorInterface $orchestrator */
            $orchestrator = $this->app->make(ModuleDiscoveryOrchestratorInterface::class);

            // Check if orchestrator has Laravel module application capability
            if (method_exists($orchestrator, 'applyLaravelModules')) {
                $orchestrator->applyLaravelModules();
            }
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Laravel Module apply error in ModuleServiceProvider: '.$e->getMessage());
            }
        }
    }
}
