<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\RouteMatch;

/**
 * Port for route matching functionality
 * 
 * Defines the contract for matching incoming requests against
 * registered routes and determining the best match.
 */
interface RouteMatcherInterface
{
    /**
     * Match a URI and method against registered routes
     * 
     * @param string $uri The request URI
     * @param string $method The HTTP method
     * @param array $context Additional context (WordPress globals, etc.)
     * @return RouteMatch|null The best matching route or null if no match
     */
    public function match(string $uri, string $method, array $context = []): ?RouteMatch;

    /**
     * Register a route for matching
     * 
     * @param Route $route The route to register
     * @return void
     */
    public function register(Route $route): void;

    /**
     * Get all routes ordered by priority (highest first)
     * 
     * @return Route[] Array of routes ordered by priority
     */
    public function getPrioritizedRoutes(): array;

    /**
     * Check if a route is registered
     * 
     * @param string $routeId The route ID
     * @return bool True if route is registered
     */
    public function hasRoute(string $routeId): bool;

    /**
     * Remove a route from the matcher
     * 
     * @param string $routeId The route ID to remove
     * @return bool True if route was removed
     */
    public function removeRoute(string $routeId): bool;

    /**
     * Get routes by criteria
     * 
     * @param array $criteria Filter criteria
     * @return Route[] Filtered routes
     */
    public function getRoutes(array $criteria = []): array;

    /**
     * Clear all registered routes
     * 
     * @return void
     */
    public function clear(): void;
}