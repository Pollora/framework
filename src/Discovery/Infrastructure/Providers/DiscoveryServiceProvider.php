<?php

declare(strict_types=1);

namespace Pollora\Discovery\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Infrastructure\Services\DiscoveryEngine;
use Pollora\Discovery\Infrastructure\Services\ServiceProviderDiscovery;
use Pollora\Discovery\UI\Console\DiscoveryClearCommand;
use Pollora\Discovery\UI\Console\DiscoveryCommand;

/**
 * Discovery Service Provider
 *
 * Registers all discovery-related services in the Laravel service container.
 * This provider handles the registration of:
 * - Discovery engine
 * - Discovery cache implementations
 * - Core discovery classes for framework features
 */
final class DiscoveryServiceProvider extends ServiceProvider
{
    /**
     * Register services in the container
     */
    public function register(): void
    {
        $this->registerDiscoveryEngine();
        $this->registerDiscoveryManager();
        $this->registerConsoleCommands();
    }

    /**
     * Bootstrap services after all providers have been registered
     */
    public function boot(): void
    {
        $this->setupDiscoveryEngine();
    }

    /**
     * Get the services provided by the provider
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            DiscoveryEngineInterface::class,
            DiscoveryManager::class,
            DiscoveryCommand::class,
            DiscoveryClearCommand::class,
        ];
    }

    /**
     * Register the discovery engine
     */
    private function registerDiscoveryEngine(): void
    {
        $this->app->singleton(DiscoveryEngineInterface::class, DiscoveryEngine::class);
    }

    /**
     * Register the discovery manager
     */
    private function registerDiscoveryManager(): void
    {
        $this->app->singleton(DiscoveryManager::class, fn ($app): \Pollora\Discovery\Application\Services\DiscoveryManager => new DiscoveryManager(
            engine: $app->make(DiscoveryEngineInterface::class)
        ));
    }

    /**
     * Setup the discovery engine with core discoveries and locations
     */
    private function setupDiscoveryEngine(): void
    {
        /** @var DiscoveryEngineInterface $engine */
        $engine = $this->app->make(DiscoveryEngineInterface::class);

        // Cache is now handled natively by Spatie's Discover class

        // Register core discovery classes
        $this->registerCoreDiscoveries($engine);

        // Add default Laravel app paths for discovery
        $this->addDefaultDiscoveryLocations($engine);
    }

    /**
     * Register core discovery classes
     */
    private function registerCoreDiscoveries(DiscoveryEngineInterface $engine): void
    {
        // Register ServiceProviderDiscovery
        $engine->addDiscovery('service_providers', ServiceProviderDiscovery::class);
    }

    /**
     * Register console commands
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(DiscoveryCommand::class);
            $this->app->singleton(DiscoveryClearCommand::class);

            $this->commands([
                DiscoveryCommand::class,
                DiscoveryClearCommand::class,
            ]);
        }
    }

    /**
     * Add default discovery locations for Laravel application
     */
    private function addDefaultDiscoveryLocations(DiscoveryEngineInterface $engine): void
    {
        // Add Laravel app directory for discovery
        $appPath = $this->app->path();
        if (is_dir($appPath)) {
            $engine->addLocation(new \Pollora\Discovery\Domain\Models\DirectoryLocation($appPath, 'App'));
        }

        // Add other common Laravel directories
        $basePath = $this->app->basePath();

        // Add app directory if different from above
        $appDir = $basePath.'/app';
        if (is_dir($appDir) && $appDir !== $appPath) {
            $engine->addLocation(new \Pollora\Discovery\Domain\Models\DirectoryLocation($appDir, 'App'));
        }
    }
}
