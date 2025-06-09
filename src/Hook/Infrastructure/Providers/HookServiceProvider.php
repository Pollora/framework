<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Discoverer\Framework\API\PolloraDiscover;
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
    public function boot(Application $app): void
    {
        $this->app = $app;
        $this->loadHooks();
    }

    /**
     * Load all discovered hooks using the new discovery system.
     */
    protected function loadHooks(): void
    {
        try {
            $hooks = PolloraDiscover::scout('hooks');
            foreach ($hooks as $hookClass) {
                $this->registerHook($hookClass);
            }
        } catch (\Throwable $e) {
            // Log error but don't break the application
            if (function_exists('error_log')) {
                error_log('Failed to load hooks: '.$e->getMessage());
            }
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
