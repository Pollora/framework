<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Modules\Application\UseCases\ApplyModulesUseCase;
use Pollora\Modules\Application\UseCases\DiscoverModulesUseCase;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Infrastructure\Services\LaravelModuleAssets;
use Pollora\Modules\Infrastructure\Services\LeanModuleMake;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Modules\Infrastructure\Services\ModuleComponentManager;
use Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Pollora\Modules\Infrastructure\Services\ModuleRouteLoader;
use Pollora\Modules\Infrastructure\Services\ModuleTemplate;
use Pollora\Modules\UI\Console\MakeModuleCommand;
use Pollora\Modules\UI\Console\ModuleConnectorCommand;
use Pollora\Modules\UI\Http\ModulesAdminPage;

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
        $this->registerLeanModuleMake();

        // Before any provider boots: WordPress loads, and fires init, while the providers boot
        $setUpModuleAssets = function (): void {
            $this->app->make(LaravelModuleAssets::class)->setUp();
        };

        $this->app->isBooted() ? $setUpModuleAssets() : $this->app->booting($setUpModuleAssets);

        $this->mergeConfigFrom(__DIR__.'/../../../../config/modules-defaults.php', 'modules');
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

        // Activation connector settings, read by nwidart while it registers: published, never merged
        $this->publishes([
            __DIR__.'/../../../../config/modules.php' => $this->app->configPath('modules.php'),
        ], 'pollora-modules');

        if ($this->app->runningInConsole()) {
            $this->commands([MakeModuleCommand::class, ModuleConnectorCommand::class]);
        }

        $this->registerModulesAdminPage();
    }

    /**
     * The Modules view of the Plugins screen.
     */
    private function registerModulesAdminPage(): void
    {
        if (! $this->app->bound(Action::class) || ! $this->app->bound(Filter::class)) {
            return;
        }

        $action = $this->app->make(Action::class);
        $filter = $this->app->make(Filter::class);

        $action->add('admin_menu', fn () => $this->app->make(ModulesAdminPage::class)->addMenuPage());
        $action->add('load-plugins_page_'.ModulesAdminPage::SLUG, fn () => $this->app->make(ModulesAdminPage::class)->handleRequest());

        $filter->add('views_plugins', fn (array $views): array => $this->app->make(ModulesAdminPage::class)->addView($views));
    }

    /**
     * Have nwidart/laravel-modules' module:make write the lean Pollora module,
     * unless the project published config/modules.php.
     */
    private function registerLeanModuleMake(): void
    {
        $this->app->singleton(ModuleTemplate::class);
        $this->app->singleton(LeanModuleMake::class, fn (Application $app): LeanModuleMake => new LeanModuleMake(
            $app->make('config'),
            $app->make(ModuleTemplate::class),
            $app->configPath('modules.php'),
        ));

        $this->app->make(LeanModuleMake::class)->applyDefaults();

        Event::listen('modules.*.created', function (string $event, array $payload): void {
            if (isset($payload[0]) && is_object($payload[0])) {
                $this->app->make(LeanModuleMake::class)->writeOver($payload[0]);
            }
        });
    }

    /**
     * Register domain contracts with their infrastructure implementations.
     */
    private function registerDomainContracts(): void
    {
        // Register ModuleAutoloader service
        $this->app->singleton(ModuleAutoloader::class, fn (Container $app): ModuleAutoloader => new ModuleAutoloader($app));

        // Register ModuleDiscoveryOrchestrator
        $this->app->singleton(ModuleDiscoveryOrchestrator::class, fn (Container $app): ModuleDiscoveryOrchestrator => new ModuleDiscoveryOrchestrator($app));

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
        $this->app->singleton(function (Application $app): DiscoverModulesUseCase {
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

        $this->app->singleton(function (Application $app): ApplyModulesUseCase {
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
        $this->app->singleton(ModuleConfigurationLoader::class, fn (Container $app): ModuleConfigurationLoader => new ModuleConfigurationLoader(
            $app,
            $app->make(ConfigRepositoryInterface::class)
        ));

        $this->app->singleton(ModuleComponentManager::class, fn (Container $app): ModuleComponentManager => new ModuleComponentManager($app));

        $this->app->singleton(ModuleAssetManager::class, fn (Container $app): ModuleAssetManager => new ModuleAssetManager($app));

        $this->app->singleton(ModuleRouteLoader::class, fn (Container $app): ModuleRouteLoader => new ModuleRouteLoader($app));
    }

    /**
     * Load helper functions.
     */
    protected function loadHelperFunctions(): void
    {
        require_once __DIR__.'/../../UI/Helpers/discovery_functions.php';
    }
}
