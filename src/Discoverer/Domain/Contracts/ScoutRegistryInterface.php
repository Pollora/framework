<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Contracts;

use Illuminate\Support\Collection;

/**
 * Contract for managing scout registration and discovery operations.
 *
 * This interface defines the core functionality for registering scouts by key
 * and executing discovery operations through a simple, generic API.
 */
interface ScoutRegistryInterface
{
    /**
     * Register a scout class with a unique key.
     *
     * @param  string  $key  The unique identifier for the scout
     * @param  string  $scoutClass  The fully qualified class name of the scout
     *
     * @throws \InvalidArgumentException When the scout class is invalid
     */
    public function register(string $key, string $scoutClass): void;

    /**
     * Execute discovery using the registered scout.
     *
     * @param  string  $key  The scout key to use for discovery
     * @return Collection<int, string> Collection of discovered class names
     *
     * @throws \InvalidArgumentException When the scout key is not found
     * @throws \RuntimeException When discovery fails
     */
    public function discover(string $key): Collection;

    /**
     * Execute discovery and automatically handle discovered classes if the scout implements HandlerScoutInterface.
     *
     * @param  string  $key  The scout key to use for discovery
     * @return Collection<int, string> Collection of discovered class names
     *
     * @throws \InvalidArgumentException When the scout key is not found
     * @throws \RuntimeException When discovery or handling fails
     */
    public function discoverAndHandle(string $key): Collection;

    /**
     * Get all registered scout keys.
     *
     * @return array<string> Array of registered scout keys
     */
    public function getRegistered(): array;

    /**
     * Check if a scout is registered for the given key.
     *
     * @param  string  $key  The scout key to check
     * @return bool True if the scout is registered, false otherwise
     */
    public function has(string $key): bool;
}
