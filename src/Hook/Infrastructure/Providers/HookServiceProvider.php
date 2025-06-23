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
}
