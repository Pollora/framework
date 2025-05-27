<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\Route;

/**
 * Port for route registry functionality
 * 
 * Defines the contract for storing and retrieving routes
 * in a persistent manner.
 */
interface RouteRegistryInterface
{
    /**
     * Register a route in the registry
     * 
     * @param Route $route The route to register
     * @return void
     */
    public function register(Route $route): void;

    /**
     * Get a route by its ID
     * 
     * @param string $routeId The route ID
     * @return Route|null The route if found
     */
    public function get(string $routeId): ?Route;

    /**
     * Get all registered routes
     * 
     * @return Route[] Array of all routes
     */
    public function all(): array;

    /**
     * Get routes filtered by criteria
     * 
     * @param array $criteria Filter criteria
     * @return Route[] Filtered routes
     */
    public function filter(array $criteria): array;

    /**
     * Check if a route is registered
     * 
     * @param string $routeId The route ID
     * @return bool True if route exists
     */
    public function has(string $routeId): bool;

    /**
     * Remove a route from the registry
     * 
     * @param string $routeId The route ID to remove
     * @return bool True if route was removed
     */
    public function remove(string $routeId): bool;

    /**
     * Clear all routes from the registry
     * 
     * @return void
     */
    public function clear(): void;

    /**
     * Get routes by type (WordPress, Laravel, etc.)
     * 
     * @param string $type The route type
     * @return Route[] Routes of the specified type
     */
    public function getByType(string $type): array;

    /**
     * Get routes by HTTP method
     * 
     * @param string $method The HTTP method
     * @return Route[] Routes that support the method
     */
    public function getByMethod(string $method): array;

    /**
     * Get routes ordered by priority
     * 
     * @param bool $descending True for highest priority first
     * @return Route[] Prioritized routes
     */
    public function getByPriority(bool $descending = true): array;

    /**
     * Count registered routes
     * 
     * @param array $criteria Optional filter criteria
     * @return int Number of routes
     */
    public function count(array $criteria = []): int;
}