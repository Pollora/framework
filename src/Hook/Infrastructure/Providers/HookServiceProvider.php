<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Hook\Domain\Contracts\Action as ActionContract;
use Pollora\Hook\Domain\Contracts\Filter as FilterContract;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Hook\UI\Console\ActionMakeCommand;
use Pollora\Hook\UI\Console\FilterMakeCommand;
use Pollora\Hook\Infrastructure\Services\HookDiscovery;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;

/**
 * Service provider for Hook feature (Infrastructure layer).
 *
 * Registers hook services, binds contracts to implementations, and integrates
 * with Laravel's service container and console commands.
 */
class HookServiceProvider extends ServiceProvider
{
    /**
     * Application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * Console detection service instance.
     */
    protected ConsoleDetectionService $consoleDetectionService;

    public function __construct($app, ?ConsoleDetectionService $consoleDetectionService = null)
    {
        parent::__construct($app);
        $this->consoleDetectionService = $consoleDetectionService ?? app(ConsoleDetectionService::class);
    }

    /**
     * Register hook-related services in the application.
     *
     * Binds hook contracts and implementations as singletons
     * in the application container.
     */
    public function register(): void
    {
        // Bind concrete classes
        $this->app->singleton(Action::class);
        $this->app->singleton(Filter::class);
        
        // Bind interfaces to implementations
        $this->app->bind(ActionContract::class, Action::class);
        $this->app->bind(FilterContract::class, Filter::class);

        // Register Hook Discovery
        $this->app->singleton(HookDiscovery::class, function ($app) {
            return new HookDiscovery(
                $app->make(ActionContract::class),
                $app->make(FilterContract::class)
            );
        });

        if ($this->consoleDetectionService->isConsole()) {
            $this->commands([
                ActionMakeCommand::class,
                FilterMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register Hook discovery with the discovery engine
        $this->registerHookDiscovery();
    }

    /**
     * Register Hook discovery with the discovery engine.
     */
    private function registerHookDiscovery(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            /** @var DiscoveryEngineInterface $engine */
            $engine = $this->app->make(DiscoveryEngineInterface::class);
            $hookDiscovery = $this->app->make(HookDiscovery::class);
            
            $engine->addDiscovery('hooks', $hookDiscovery);
        }
    }
}
