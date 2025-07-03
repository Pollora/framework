<?php

declare(strict_types=1);

namespace Pollora\Discovery\Infrastructure\Adapters;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Pollora\Discovery\Domain\Contracts\DiscoveryCacheInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryItemsInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Models\DiscoveryItems;

/**
 * Laravel Discovery Cache
 *
 * Implementation of discovery cache using Laravel's cache system.
 * Provides persistent caching of discovery results to improve performance.
 *
 * @package Pollora\Discovery\Infrastructure\Adapters
 */
final class LaravelDiscoveryCache implements DiscoveryCacheInterface
{
    /**
     * Cache key prefix for discovery items
     */
    private const CACHE_PREFIX = 'pollora.discovery.';

    /**
     * Default cache TTL in seconds (24 hours)
     */
    private const DEFAULT_TTL = 86400;

    /**
     * Create a new Laravel discovery cache
     *
     * @param CacheRepository $cache The Laravel cache repository
     * @param string $prefix Optional cache key prefix
     * @param int $defaultTtl Default time-to-live in seconds
     */
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $prefix = self::CACHE_PREFIX,
        private readonly int $defaultTtl = self::DEFAULT_TTL
    ) {}

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return $this->cache->has($this->prefixKey($key));
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): ?DiscoveryItemsInterface
    {
        $cached = $this->cache->get($this->prefixKey($key));

        if ($cached === null) {
            return null;
        }

        // Unserialize the cached data
        if (is_array($cached)) {
            $items = new DiscoveryItems();
            $items->__unserialize($cached);
            return $items;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $key, DiscoveryItemsInterface $items, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $serializedItems = $items->__serialize();

        return $this->cache->put($this->prefixKey($key), $serializedItems, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): bool
    {
        return $this->cache->forget($this->prefixKey($key));
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        // Laravel doesn't provide a way to flush by prefix, so we'll flush all
        // In a real implementation, you might want to track keys and delete individually
        return $this->cache->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function generateKey(string $discoveryIdentifier, array $locations): string
    {
        // Create a deterministic key based on discovery and locations
        $locationPaths = [];
        
        foreach ($locations as $location) {
            if ($location instanceof DiscoveryLocationInterface) {
                $locationPaths[] = $location->getPath();
            } else {
                // Fallback for non-location objects
                $locationPaths[] = 'unknown';
            }
        }

        sort($locationPaths); // Ensure consistent ordering

        $locationsHash = md5(implode('|', $locationPaths));

        return "{$discoveryIdentifier}.{$locationsHash}";
    }

    /**
     * Add prefix to cache key
     *
     * @param string $key The original key
     *
     * @return string The prefixed key
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }
}