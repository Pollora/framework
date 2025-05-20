<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Interface for the router component.
 * 
 * Defines the contract for the main router component that manages
 * route registration, finding, and dispatching.
 */
interface RouterInterface
{
    /**
     * Create a new route entity.
     *
     * @param array<int, string> $methods HTTP methods
     * @param string $uri URI pattern
     * @param mixed $action Route action
     * @return RouteEntity The created route entity
     */
    public function newRoute(array $methods, string $uri, $action): RouteEntity;
    
    /**
     * Find the route matching a given request.
     *
     * @param mixed $request The request to match
     * @return RouteEntity|null The matching route or null if not found
     */
    public function findRoute($request): ?RouteEntity;
    
    /**
     * Set WordPress conditions for routes.
     *
     * @param array<string, mixed> $conditions Mapping of condition signatures to routes
     * @return void
     */
    public function setConditions(array $conditions = []): void;
    
    /**
     * Add a route to the collection.
     *
     * @param RouteEntity $route The route to add
     * @return void
     */
    public function addRoute(RouteEntity $route): void;
} 