<?php

declare(strict_types=1);

namespace Pollora\Modules;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Pollora\Discoverer\Scouts\ThemeServiceProviderScout;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Providers\ModuleServiceProvider as InfrastructureModuleServiceProvider;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Modules\Infrastructure\Services\ModuleBootstrap;
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
        // Register infrastructure provider for generic module functionality
        $this->app->register(InfrastructureModuleServiceProvider::class);

        // Register ModuleAutoloader service
        $this->app->singleton(ModuleAutoloader::class, function ($app) {
            return new ModuleAutoloader($app);
        });

        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/config/modules.php', 'modules');
    }

    public function boot(): void
    {
        // Register ModuleManifest service
        $this->app->singleton(ModuleManifest::class, function ($app) {
            // Try to get the scout for provider discovery
            $scout = null;
            try {
                $scout = new ThemeServiceProviderScout($app);
            } catch (\Exception $e) {
                // Continue without scout if it fails
            }

            return new ModuleManifest(
                new Filesystem,
                $this->getModulePaths(),
                $this->getCachedModulePath(),
                $app->make(ModuleRepositoryInterface::class),
                $scout
            );
        });

        // Register ModuleBootstrap service
        $this->app->singleton(ModuleBootstrap::class, function ($app) {
            return new ModuleBootstrap(
                $app,
                $app->make(ModuleRepositoryInterface::class)
            );
        });

        // Register and boot modules if a repository is available
        if ($this->app->bound(ModuleRepositoryInterface::class)) {
            $bootstrap = $this->app->make(ModuleBootstrap::class);

            // Register modules
            $bootstrap->registerModules();

            // Register migrations and translations
            $bootstrap->registerMigrations();
            $bootstrap->registerTranslations();

            // Boot modules on next cycle
            $this->app->booted(function () use ($bootstrap) {
                $bootstrap->bootModules();
            });
        }
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
