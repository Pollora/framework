<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\Hook\Adapter\Out\WordPress\Filter;
use Pollora\Hook\Domain\Contract\Action as ActionContract;
use Pollora\Hook\Domain\Contract\CallbackResolverInterface;
use Pollora\Hook\Domain\Contract\Filter as FilterContract;
use Pollora\Hook\Infrastructure\Services\ContainerCallbackResolver;
use Pollora\Hook\Infrastructure\Services\HookDiscovery;
use Pollora\Hook\UI\Console\ActionMakeCommand;
use Pollora\Hook\UI\Console\FilterMakeCommand;

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

    public function __construct(Application $app, ?ConsoleDetectionService $consoleDetectionService = null)
    {
        parent::__construct($app);
        $this->consoleDetectionService = $consoleDetectionService ?? resolve(ConsoleDetectionService::class);
    }

    /**
     * Register hook-related services in the application.
     *
     * Binds hook contracts and implementations as singletons
     * in the application container.
     */
    public function register(): void
    {
        // Callback resolver for dependency injection in hook callbacks
        $this->app->singleton(CallbackResolverInterface::class, fn (Application $app): ContainerCallbackResolver => new ContainerCallbackResolver($app));

        // Bind concrete classes with resolver injection
        $this->app->singleton(Action::class, function (Application $app): Action {
            $action = new Action;
            $action->setCallbackResolver($app->make(CallbackResolverInterface::class));

            return $action;
        });
        $this->app->singleton(Filter::class, function (Application $app): Filter {
            $filter = new Filter;
            $filter->setCallbackResolver($app->make(CallbackResolverInterface::class));

            return $filter;
        });

        // Alias interfaces to singleton implementations so all resolution
        // paths (Facade, DI, manual make) return the same instance
        $this->app->alias(Action::class, ActionContract::class);
        $this->app->alias(Filter::class, FilterContract::class);

        // Register Hook Discovery
        $this->app->singleton(HookDiscovery::class, fn (Application $app): HookDiscovery => new HookDiscovery(
            $app->make(ActionContract::class),
            $app->make(FilterContract::class)
        ));

        if ($this->consoleDetectionService->isConsole()) {
            $this->commands([
                ActionMakeCommand::class,
                FilterMakeCommand::class,
            ]);
        }
    }
}
