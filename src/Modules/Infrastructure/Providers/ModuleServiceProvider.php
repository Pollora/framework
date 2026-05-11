<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Modules\Application\UseCases\ApplyModulesUseCase;
use Pollora\Modules\Application\UseCases\DiscoverModulesUseCase;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Modules\Infrastructure\Services\ModuleComponentManager;
use Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;

/**
 * Main service provider for the generic module system.
 *
 * This provider follows the nwidart/laravel-modules pattern but adapted for our architecture.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerDomainContracts();
        $this->registerUseCases();
        $this->registerApplicationServices();

        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/modules.php', 'modules');
    }

    public function boot(): void
    {
        // Load helper functions
        $this->loadHelperFunctions();

        // Discover and apply all modules
        $this->app->make(DiscoverModulesUseCase::class)->execute();
        $this->app->make(ApplyModulesUseCase::class)->execute();

        // Fire event when modules are ready
        $this->app->booted(function (): void {
            Event::dispatch('modules.routes.registered');
        });
    }

    /**
     * Register domain contracts with their infrastructure implementations.
     */
    private function registerDomainContracts(): void
    {
        // Register ModuleAutoloader service
        $this->app->singleton(ModuleAutoloader::class, fn ($app): ModuleAutoloader => new ModuleAutoloader($app));

        // Register ModuleDiscoveryOrchestrator
        $this->app->singleton(ModuleDiscoveryOrchestrator::class, fn ($app): ModuleDiscoveryOrchestrator => new ModuleDiscoveryOrchestrator($app));

        // Register interface binding
        $this->app->bind(ModuleDiscoveryOrchestratorInterface::class, ModuleDiscoveryOrchestrator::class);

        // Register alias for easier access
        $this->app->alias(ModuleDiscoveryOrchestrator::class, 'modules.discovery');
    }

    /**
     * Register application use cases.
     */
    private function registerUseCases(): void
    {
        $this->app->singleton(DiscoverModulesUseCase::class, function ($app): DiscoverModulesUseCase {
            $logger = null;
            try {
                $logger = $app->make('log');
            } catch (\Exception) {
                // Logger not available during early bootstrap
            }

            return new DiscoverModulesUseCase(
                $app->make(ModuleDiscoveryOrchestrator::class),
                $logger
            );
        });

        $this->app->singleton(ApplyModulesUseCase::class, function ($app): ApplyModulesUseCase {
            $logger = null;
            try {
                $logger = $app->make('log');
            } catch (\Exception) {
                // Logger not available during early bootstrap
            }

            return new ApplyModulesUseCase(
                $app->make(ModuleDiscoveryOrchestrator::class),
                $logger
            );
        });
    }

    /**
     * Register application services.
     */
    private function registerApplicationServices(): void
    {
        $this->app->singleton(ModuleConfigurationLoader::class, fn ($app): ModuleConfigurationLoader => new ModuleConfigurationLoader(
            $app,
            $app->make(ConfigRepositoryInterface::class)
        ));

        $this->app->singleton(ModuleComponentManager::class, fn ($app): ModuleComponentManager => new ModuleComponentManager($app));

        $this->app->singleton(ModuleAssetManager::class, fn ($app): ModuleAssetManager => new ModuleAssetManager($app));
    }

    /**
     * Load helper functions.
     */
    protected function loadHelperFunctions(): void
    {
        require_once __DIR__.'/../../UI/Helpers/discovery_functions.php';
    }
}
