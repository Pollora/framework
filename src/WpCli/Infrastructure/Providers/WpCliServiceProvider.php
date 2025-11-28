<?php

declare(strict_types=1);

namespace Pollora\WpCli\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\WpCli\Application\Services\WpCliService;
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
        $this->consoleDetectionService = $consoleDetectionService ?? app(ConsoleDetectionService::class);
    }

    /**
     * Register the WP CLI services.
     */
    public function register(): void
    {
        // Register the WP CLI service
        $this->app->singleton(WpCliService::class, function ($app): WpCliService {
            return new WpCliService();
        });

        // Register WP CLI Discovery
        $this->app->singleton(WpCliDiscovery::class, function ($app): WpCliDiscovery {
            return new WpCliDiscovery(
                $app->make(WpCliService::class)
            );
        });

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
        // Register WP CLI discovery with the discovery engine
        $this->registerWpCliDiscovery();

        // Initialize commands if WP CLI is available
        $this->initializeWpCliCommands();
    }

    /**
     * Register WP CLI discovery with the discovery engine.
     */
    private function registerWpCliDiscovery(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            /** @var DiscoveryEngineInterface $engine */
            $engine = $this->app->make(DiscoveryEngineInterface::class);
            $wpCliDiscovery = $this->app->make(WpCliDiscovery::class);

            $engine->addDiscovery('wp_cli_commands', $wpCliDiscovery);
        }
    }

    /**
     * Initialize WP CLI commands if WP CLI is available.
     */
    private function initializeWpCliCommands(): void
    {
        // Only initialize if we're in a WP CLI context
        if (\defined('WP_CLI') && WP_CLI) {
            $wpCliService = $this->app->make(WpCliService::class);
            $wpCliService->initializeCommands();
        }
    }
}