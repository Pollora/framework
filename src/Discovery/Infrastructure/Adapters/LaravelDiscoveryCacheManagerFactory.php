<?php

declare(strict_types=1);

namespace Pollora\Discovery\Infrastructure\Adapters;

use Illuminate\Contracts\Container\Container;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discovery\Infrastructure\Services\DiscoveryCacheManager;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\LaravelDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;

/**
 * Builds the discovery cache manager from a Laravel application.
 *
 * In debug mode the cache is off and slow scans are reported, so a slow
 * directory is noticed while it is paid on every request. Otherwise the
 * driver comes from `structure-discoverer.cache`, defaulting to Laravel's
 * cache under the `pollora` prefix.
 */
final readonly class LaravelDiscoveryCacheManagerFactory
{
    public function __construct(
        private Container $container,
        private DebugDetectorInterface $debugDetector
    ) {}

    public function make(): DiscoveryCacheManager
    {
        $isDebugMode = $this->debugDetector->isDebugMode();

        return new DiscoveryCacheManager(
            cacheDriver: $isDebugMode ? new NullDiscoverCacheDriver : $this->resolveCacheDriver(),
            logger: $this->resolveLogger(),
            warnOnSlowScans: $isDebugMode,
        );
    }

    private function resolveCacheDriver(): DiscoverCacheDriver
    {
        /** @var array{driver?: class-string<DiscoverCacheDriver>, store?: string|null} $cacheConfig */
        $cacheConfig = $this->container->bound('config')
            ? (array) $this->container->make('config')->get('structure-discoverer.cache', [])
            : [];

        $driverClass = $cacheConfig['driver'] ?? LaravelDiscoverCacheDriver::class;
        $store = $cacheConfig['store'] ?? null;

        if ($driverClass === LaravelDiscoverCacheDriver::class) {
            return new LaravelDiscoverCacheDriver(prefix: 'pollora', store: $store);
        }

        try {
            return $this->container->make($driverClass);
        } catch (\Throwable) {
            return new LaravelDiscoverCacheDriver(prefix: 'pollora', store: $store);
        }
    }

    private function resolveLogger(): ?LoggerInterface
    {
        try {
            return $this->container->bound(LoggerInterface::class)
                ? $this->container->make(LoggerInterface::class)
                : null;
        } catch (\Throwable) {
            // A missing logger must not keep discovery from starting.
            return null;
        }
    }
}
