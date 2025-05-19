<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
use Pollora\Discoverer\Domain\Contracts\ScoutInterface;
use Pollora\Discoverer\Domain\Services\DiscoveryService;
use Pollora\Discoverer\Infrastructure\Repositories\InMemoryDiscoveryRegistry;
use Pollora\Discoverer\Infrastructure\Services\Scouts\AttributeScout;
use Pollora\Discoverer\Infrastructure\Services\Scouts\HookScout;
use Pollora\Discoverer\Infrastructure\Services\Scouts\PostTypeScout;
use Pollora\Discoverer\Infrastructure\Services\Scouts\RestScout;
use Pollora\Discoverer\Infrastructure\Services\Scouts\TaxonomyScout;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;

/**
 * Service provider for class auto-discovery functionality.
 */
final class DiscovererServiceProvider extends ServiceProvider
{
    /**
     * Scout configurations with their custom directory mappings.
     * Key is the scout class, value is a callback to define custom directories.
     *
     * @var array<class-string<ScoutInterface>, callable>
     */
    protected array $scoutConfigs = [];

    /**
     * Default scouts that should always be registered.
     *
     * @var array<class-string<ScoutInterface>>
     */
    protected array $scouts = [
        HookScout::class,
        AttributeScout::class,
        PostTypeScout::class,
        TaxonomyScout::class,
        RestScout::class,
    ];

    public function register(): void
    {
        // Register the main registry (volatile memory)
        $this->app->singleton(DiscoveryRegistryInterface::class, InMemoryDiscoveryRegistry::class);

        // Create a cache service for Spatie's Structure Discoverer
        $this->app->singleton(DiscoverCacheDriver::class, function (Container $app) {
            // Use Laravel cache if available
            if (class_exists(\Spatie\StructureDiscoverer\Cache\LaravelDiscoverCacheDriver::class)
                && method_exists($app, 'environment')) {
                return new \Spatie\StructureDiscoverer\Cache\LaravelDiscoverCacheDriver;
            }

            // Otherwise use file cache
            $cachePath = defined('WP_CONTENT_DIR')
                ? WP_CONTENT_DIR.'/cache/pollora'
                : __DIR__.'/../../../../storage/cache/pollora';

            if (! is_dir($cachePath) && ! mkdir($cachePath, 0755, true) && ! is_dir($cachePath)) {
                throw new \RuntimeException("Could not create cache directory: {$cachePath}");
            }

            return new FileDiscoverCacheDriver($cachePath);
        });

        // Detect generic application paths
        $appPath = '';
        $modulesPath = '';

        // For Laravel
        if (function_exists('app_path')) {
            $appPath = app_path();
        }

        // For custom modules
        if ($this->app->bound('modules')) {
            try {
                $modulesPath = $this->app->make('modules')->getPath();
            } catch (\Exception $e) {
                // Ignore error
            }
        }

        // Register scouts with their cache injector
        foreach ($this->scouts as $scoutClass) {
            $this->app->singleton($scoutClass, function (Container $app) use ($scoutClass, $appPath, $modulesPath) {
                // Initialize default directories
                $directories = [];

                // Use default directories defined by the scout class if method exists
                if (method_exists($scoutClass, 'getDefaultDirectories')) {
                    $directories = call_user_func([$scoutClass, 'getDefaultDirectories'], $appPath, $modulesPath);
                } else {
                    // Fallback for scouts without getDefaultDirectories method
                    if ($appPath) {
                        $directories[] = $appPath;
                    }
                    if ($modulesPath) {
                        $directories[] = $modulesPath;
                    }
                }

                // If a custom configuration exists for this scout, apply it
                if (isset($this->scoutConfigs[$scoutClass])) {
                    $customDirs = call_user_func($this->scoutConfigs[$scoutClass], $appPath, $modulesPath);
                    if (is_array($customDirs) && ! empty($customDirs)) {
                        $directories = $customDirs;
                    }
                }

                return new $scoutClass(
                    $app,
                    $directories
                );
            });
        }

        // Register the discovery service
        $this->app->singleton(DiscoveryService::class, function (Container $app) {
            $scouts = array_map(
                fn (string $scoutClass) => $app->make($scoutClass),
                $this->scouts
            );

            return new DiscoveryService(
                $app->make(DiscoveryRegistryInterface::class),
                $scouts
            );
        });
    }

    /**
     * Allow setting custom directories for a specific scout.
     *
     * @param  class-string<ScoutInterface>  $scoutClass  The scout class to configure
     * @param  callable  $directoryCallback  Callback that returns an array of directories
     * @return $this
     */
    public function setScoutDirectories(string $scoutClass, callable $directoryCallback): self
    {
        $this->scoutConfigs[$scoutClass] = $directoryCallback;

        return $this;
    }

    public function boot(): void
    {
        $this->app->make(DiscoveryService::class)->discoverAndRegister();
    }
}
