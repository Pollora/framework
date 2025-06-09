<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
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
        $this->app->singleton(Action::class, Action::class);
        $this->app->singleton(Filter::class, Filter::class);

        if ($this->consoleDetectionService->isConsole()) {
            $this->commands([
                ActionMakeCommand::class,
                FilterMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap hook services.
     *
     * Instantiates and registers all hooks discovered by the Discoverer system.
     */
    public function boot(Application $app, DiscoveryRegistryInterface $registry): void
    {
        $this->app = $app;
        $this->loadHooks($registry);
    }

    /**
     * Load all discovered hooks.
     *
     * @param  DiscoveryRegistry  $registry  The discovery registry
     */
    protected function loadHooks(DiscoveryRegistryInterface $registry): void
    {
        $hooks = $registry->getByType('hook');
        foreach ($hooks as $hookClass) {
            $this->registerHook($hookClass->getClassName());
        }
    }

    /**
     * Register an individual hook class.
     *
     * Creates an instance of the hook class and registers its 'register' method
     * as a WordPress action if the method exists.
     *
     * @param  string  $hookClass  The fully qualified class name of the hook to register
     */
    protected function registerHook(string $hookClass): void
    {
        $this->app->make($hookClass);
    }
}
