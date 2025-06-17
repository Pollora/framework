<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Framework\API;

use Illuminate\Support\Collection;
use Pollora\Discoverer\Domain\Services\DiscoveryService;

/**
 * Simple API facade for the Pollora discovery system.
 *
 * Provides a clean, expressive interface for registering scouts and executing
 * discovery operations. This class delegates all operations to the DiscoveryService
 * while maintaining a simplified API surface.
 *
 * @example
 * ```php
 * // Register a scout
 * PolloraDiscover::register('my_scout', MyScout::class);
 *
 * // Execute discovery
 * $classes = PolloraDiscover::scout('my_scout');
 *
 * // Execute discovery and handle automatically
 * $classes = PolloraDiscover::scoutAndHandle('my_scout');
 *
 * // Check if scout exists
 * if (PolloraDiscover::has('my_scout')) {
 *     // ...
 * }
 * ```
 */
final class PolloraDiscover
{
    /**
     * Register a scout with the given key.
     *
     * @param  string  $key  The unique identifier for the scout
     * @param  string  $scoutClass  The fully qualified class name of the scout
     *
     * @throws \InvalidArgumentException When the scout class is invalid
     */
    public static function register(string $key, string $scoutClass): void
    {
        DiscoveryService::register($key, $scoutClass);
    }

    /**
     * Execute discovery using the registered scout.
     *
     * @param  string  $key  The scout key to use for discovery
     * @return Collection<int, string> Collection of discovered class names
     *
     * @throws \InvalidArgumentException When the scout key is not found
     * @throws \RuntimeException When discovery fails
     */
    public static function scout(string $key): Collection
    {
        return DiscoveryService::scout($key);
    }

    /**
     * Execute discovery and automatically handle discovered classes if the scout implements HandlerScoutInterface.
     *
     * @param  string  $key  The scout key to use for discovery
     * @return Collection<int, string> Collection of discovered class names
     *
     * @throws \InvalidArgumentException When the scout key is not found
     * @throws \RuntimeException When discovery or handling fails
     */
    public static function scoutAndHandle(string $key): Collection
    {
        return DiscoveryService::scoutAndHandle($key);
    }

    /**
     * Get all registered scout keys.
     *
     * @return array<string> Array of registered scout keys
     */
    public static function registered(): array
    {
        return DiscoveryService::registered();
    }

    /**
     * Check if a scout is registered for the given key.
     *
     * @param  string  $key  The scout key to check
     * @return bool True if the scout is registered, false otherwise
     */
    public static function has(string $key): bool
    {
        return DiscoveryService::has($key);
    }
}
