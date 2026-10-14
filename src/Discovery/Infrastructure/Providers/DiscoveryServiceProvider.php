<?php

declare(strict_types=1);

namespace Pollora\Discovery\Infrastructure\Providers;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\InstanceResolverInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Domain\Models\DirectoryLocation;
use Pollora\Discovery\Infrastructure\Adapters\LaravelDiscoveryCacheManagerFactory;
use Pollora\Discovery\Infrastructure\Adapters\LaravelInstanceResolver;
use Pollora\Discovery\Infrastructure\Services\DiscoveryCacheManager;
use Pollora\Discovery\Infrastructure\Services\DiscoveryEngine;
use Pollora\Discovery\Infrastructure\Services\DiscoveryRegistrar;
use Pollora\Discovery\Infrastructure\Services\InstancePool;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
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
        $this->mergeConfigFrom(__DIR__.'/../../../../config/discovery.php', 'discovery');

        $this->registerOptimizedServices();
        $this->registerDiscoveryEngine();
        $this->registerDiscoveryManager();
        $this->registerConsoleCommands();
    }

    /**
     * Bootstrap services after all providers have been registered
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../../../config/discovery.php' => config_path('discovery.php'),
        ], 'pollora-config');

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
            ReflectionCacheInterface::class,
            InstanceResolverInterface::class,
            InstancePool::class,
            DiscoveryCacheManager::class,
            DiscoveryCommand::class,
            DiscoveryClearCommand::class,
        ];
    }

    /**
     * Register optimized services for discovery performance
     */
    private function registerOptimizedServices(): void
    {
        // Discovery builds classes through Laravel's container
        $this->app->singleton(InstanceResolverInterface::class, fn (Container $app): LaravelInstanceResolver => new LaravelInstanceResolver($app));

        // Register reflection cache as singleton for maximum efficiency
        $this->app->singleton(ReflectionCacheInterface::class, fn (Container $app): ReflectionCache => new ReflectionCache(
            $app->make(InstanceResolverInterface::class)
        ));

        // Register instance pool as singleton to maintain state across discoveries
        $this->app->singleton(InstancePool::class, fn (Container $app): InstancePool => new InstancePool(
            $app->make(InstanceResolverInterface::class)
        ));

        // One cache manager, shared by the engine and the doctor's cache check
        $this->app->singleton(DiscoveryCacheManager::class, fn (Container $app): DiscoveryCacheManager => (new LaravelDiscoveryCacheManagerFactory(
            $app,
            $app->make(DebugDetectorInterface::class)
        ))->make());

        // Register ServiceProviderDiscovery so the DiscoveryRegistrar can find it
        $this->app->singleton(ServiceProviderDiscovery::class);
    }

    /**
     * Register the discovery engine with optimized services
     */
    private function registerDiscoveryEngine(): void
    {
        $this->app->singleton(DiscoveryEngineInterface::class, fn (Container $app): DiscoveryEngine => new DiscoveryEngine(
            resolver: $app->make(InstanceResolverInterface::class),
            reflectionCache: $app->make(ReflectionCacheInterface::class),
            instancePool: $app->make(InstancePool::class),
            cacheManager: $app->make(DiscoveryCacheManager::class),
            registrar: new DiscoveryRegistrar($app),
            skipClasses: (array) config('discovery.skip_classes', []),
            skipPaths: (array) config('discovery.skip_paths', []),
        ));
    }

    /**
     * Register the discovery manager
     */
    private function registerDiscoveryManager(): void
    {
        $this->app->singleton(DiscoveryManager::class, fn (Container $app): DiscoveryManager => new DiscoveryManager(
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

        // Add default Laravel app paths for discovery
        $this->addDefaultDiscoveryLocations($engine);
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
        if (! is_dir($appPath)) {
            return;
        }

        $engine->addLocation(new DirectoryLocation($appPath));
    }
}
