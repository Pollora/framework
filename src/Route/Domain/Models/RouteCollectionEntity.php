<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

/**
 * A collection of routes in the domain layer.
 *
 * This is a framework-agnostic representation of a route collection
 * with capabilities for managing WordPress-specific routes.
 */
class RouteCollectionEntity
{
    /**
     * All of the routes keyed by HTTP method.
     *
     * @var array<string, array<string, RouteEntity>>
     */
    private array $routes = [];

    /**
     * All of the routes regardless of HTTP method.
     *
     * @var array<string, RouteEntity>
     */
    private array $allRoutes = [];

    /**
     * Add a route to the collection.
     */
    public function addRoute(RouteEntity $route): void
    {
        $domainAndUri = $this->buildDomainAndUri($route);

        foreach ($route->getMethods() as $method) {
            $this->routes[$method][$domainAndUri] = $route;
            $this->allRoutes[$method.$domainAndUri] = $route;
        }
    }

    /**
     * Get all routes in the collection.
     *
     * @return array<RouteEntity>
     */
    public function getRoutes(): array
    {
        return array_values($this->allRoutes);
    }

    /**
     * Get routes for a specific method.
     *
     * @return array<string, RouteEntity>
     */
    public function getRoutesByMethod(string $method): array
    {
        return $this->routes[$method] ?? [];
    }

    /**
     * Build a unique identifier for the route based on domain, URI, and condition parameters.
     */
    private function buildDomainAndUri(RouteEntity $route): string
    {
        $domainAndUri = $route->getDomain().$route->getUri();

        if ($route->hasCondition() && ! empty($route->getConditionParameters())) {
            $domainAndUri .= serialize($route->getConditionParameters());
        }

        return $domainAndUri;
    }
}
