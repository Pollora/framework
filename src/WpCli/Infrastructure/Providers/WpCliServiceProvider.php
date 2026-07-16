<?php

declare(strict_types=1);

namespace Pollora\WpCli\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Infrastructure\Adapters\WpCliAdapter;
use Pollora\WpCli\Infrastructure\Services\WpCliDiscovery;
use Pollora\WpCli\UI\Console\WpCliMakeCommand;

/**
 * Service provider for WP CLI functionality.
 *
 * This provider registers all necessary services for WP CLI command discovery
 * and registration, following hexagonal architecture principles and dependency
 * injection patterns.
 */
class WpCliServiceProvider extends ServiceProvider
{
    /**
     * Console detection service instance.
     */
    protected ConsoleDetectionService $consoleDetectionService;

    public function __construct($app, ?ConsoleDetectionService $consoleDetectionService = null)
    {
        parent::__construct($app);
        $this->consoleDetectionService = $consoleDetectionService ?? resolve(ConsoleDetectionService::class);
    }

    /**
     * Register the WP CLI services.
     */
    public function register(): void
    {
        // Register the WP CLI adapter (Infrastructure layer)
        $this->app->singleton(WpCliAdapter::class, fn ($app): WpCliAdapter => new WpCliAdapter);

        // Register the WP CLI service (Application layer)
        $this->app->singleton(WpCliService::class, fn ($app): WpCliService => new WpCliService(
            $app->make(WpCliAdapter::class)
        ));

        // Register WP CLI Discovery (Infrastructure layer)
        $this->app->singleton(WpCliDiscovery::class, fn ($app): WpCliDiscovery => new WpCliDiscovery(
            $app->make(WpCliService::class)
        ));

        // Register console commands
        if ($this->consoleDetectionService->isConsole()) {
            $this->commands([
                WpCliMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (\defined('WP_CLI') && WP_CLI) {
            $this->app->make(WpCliService::class)->initializeCommands();
        }
    }
}
