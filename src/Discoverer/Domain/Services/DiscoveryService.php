<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Services;

use Illuminate\Support\Collection;
use Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface;

/**
 * Core discovery service providing a simple API for scout registration and execution.
 *
 * This service acts as the main entry point for the discovery system, providing
 * static methods for easy access to scout registration and discovery operations.
 * It follows the facade pattern for a clean, expressive API.
 */
final class DiscoveryService
{
    /**
     * The scout registry instance.
     */
    private static ?ScoutRegistryInterface $registry = null;

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
        self::getRegistry()->register($key, $scoutClass);
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
        return self::getRegistry()->discover($key);
    }

    /**
     * Get all registered scout keys.
     *
     * @return array<string> Array of registered scout keys
     */
    public static function registered(): array
    {
        return self::getRegistry()->getRegistered();
    }

    /**
     * Check if a scout is registered for the given key.
     *
     * @param  string  $key  The scout key to check
     * @return bool True if the scout is registered, false otherwise
     */
    public static function has(string $key): bool
    {
        return self::getRegistry()->has($key);
    }

    /**
     * Get the scout registry instance from the application container.
     *
     * @return ScoutRegistryInterface The registry instance
     *
     * @throws \RuntimeException When the registry cannot be resolved
     */
    private static function getRegistry(): ScoutRegistryInterface
    {
        if (self::$registry === null) {
            if (! function_exists('app')) {
                throw new \RuntimeException('Laravel application container is not available');
            }

            try {
                self::$registry = app(ScoutRegistryInterface::class);
            } catch (\Throwable $e) {
                throw new \RuntimeException('Failed to resolve scout registry from container: '.$e->getMessage(), 0, $e);
            }
        }

        return self::$registry;
    }

    /**
     * Set the registry instance for testing purposes.
     *
     * @param  ScoutRegistryInterface|null  $registry  The registry instance to use
     *
     * @internal This method is for testing purposes only
     */
    public static function setRegistry(?ScoutRegistryInterface $registry): void
    {
        self::$registry = $registry;
    }
}
