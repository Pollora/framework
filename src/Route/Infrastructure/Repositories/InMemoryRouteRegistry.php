<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Repositories;

use Pollora\Route\Domain\Contracts\RouteRegistryInterface;
use Pollora\Route\Domain\Models\Route;

/**
 * In-memory implementation of route registry
 * 
 * Stores routes in memory for fast access during request processing.
 * Suitable for runtime route registration and testing.
 */
final class InMemoryRouteRegistry implements RouteRegistryInterface
{
    /**
     * @var Route[] Routes indexed by ID
     */
    private array $routes = [];

    /**
     * @var array Routes indexed by type for fast filtering
     */
    private array $routesByType = [
        'wordpress' => [],
        'laravel' => [],
        'custom' => [],
    ];

    /**
     * @var array Routes indexed by HTTP method
     */
    private array $routesByMethod = [];

    /**
     * Register a route in the registry
     */
    public function register(Route $route): void
    {
        $routeId = $route->getId();
        
        // Store by ID
        $this->routes[$routeId] = $route;
        
        // Index by type
        $type = $route->isWordPressRoute() ? 'wordpress' : 'laravel';
        $this->routesByType[$type][$routeId] = $route;
        
        // Index by HTTP methods
        foreach ($route->getMethods() as $method) {
            $method = strtoupper($method);
            if (!isset($this->routesByMethod[$method])) {
                $this->routesByMethod[$method] = [];
            }
            $this->routesByMethod[$method][$routeId] = $route;
        }
    }

    /**
     * Get a route by its ID
     */
    public function get(string $routeId): ?Route
    {
        return $this->routes[$routeId] ?? null;
    }

    /**
     * Get all registered routes
     */
    public function all(): array
    {
        return array_values($this->routes);
    }

    /**
     * Get routes filtered by criteria
     */
    public function filter(array $criteria): array
    {
        $routes = $this->all();
        
        foreach ($criteria as $key => $value) {
            $routes = array_filter($routes, function (Route $route) use ($key, $value) {
                return match ($key) {
                    'type' => $this->matchesType($route, $value),
                    'method' => $this->matchesMethod($route, $value),
                    'is_wordpress_route' => $route->isWordPressRoute() === $value,
                    'condition_type' => $route->getCondition()->getType() === $value,
                    'has_parameters' => $route->getCondition()->hasParameters() === $value,
                    'priority_min' => $route->getPriority() >= $value,
                    'priority_max' => $route->getPriority() <= $value,
                    'middleware' => $this->hasMiddleware($route, $value),
                    default => true
                };
            });
        }
        
        return array_values($routes);
    }

    /**
     * Check if a route is registered
     */
    public function has(string $routeId): bool
    {
        return isset($this->routes[$routeId]);
    }

    /**
     * Remove a route from the registry
     */
    public function remove(string $routeId): bool
    {
        if (!isset($this->routes[$routeId])) {
            return false;
        }
        
        $route = $this->routes[$routeId];
        
        // Remove from main storage
        unset($this->routes[$routeId]);
        
        // Remove from type index
        $type = $route->isWordPressRoute() ? 'wordpress' : 'laravel';
        unset($this->routesByType[$type][$routeId]);
        
        // Remove from method indexes
        foreach ($route->getMethods() as $method) {
            $method = strtoupper($method);
            unset($this->routesByMethod[$method][$routeId]);
        }
        
        return true;
    }

    /**
     * Clear all routes from the registry
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->routesByType = [
            'wordpress' => [],
            'laravel' => [],
            'custom' => [],
        ];
        $this->routesByMethod = [];
    }

    /**
     * Get routes by type
     */
    public function getByType(string $type): array
    {
        return array_values($this->routesByType[$type] ?? []);
    }

    /**
     * Get routes by HTTP method
     */
    public function getByMethod(string $method): array
    {
        $method = strtoupper($method);
        return array_values($this->routesByMethod[$method] ?? []);
    }

    /**
     * Get routes ordered by priority
     */
    public function getByPriority(bool $descending = true): array
    {
        $routes = $this->all();
        
        usort($routes, function (Route $a, Route $b) use ($descending) {
            $comparison = $b->getPriority() <=> $a->getPriority();
            return $descending ? $comparison : -$comparison;
        });
        
        return $routes;
    }

    /**
     * Count registered routes
     */
    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            return count($this->routes);
        }
        
        return count($this->filter($criteria));
    }

    /**
     * Get registry statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_routes' => count($this->routes),
            'wordpress_routes' => count($this->routesByType['wordpress']),
            'laravel_routes' => count($this->routesByType['laravel']),
            'custom_routes' => count($this->routesByType['custom']),
            'methods' => array_keys($this->routesByMethod),
            'method_counts' => array_map('count', $this->routesByMethod),
        ];
    }

    /**
     * Export routes for debugging
     */
    public function export(): array
    {
        return array_map(function (Route $route) {
            return [
                'id' => $route->getId(),
                'uri' => $route->getUri(),
                'methods' => $route->getMethods(),
                'is_wordpress_route' => $route->isWordPressRoute(),
                'condition' => [
                    'type' => $route->getCondition()->getType(),
                    'condition' => $route->getCondition()->getCondition(),
                    'parameters' => $route->getCondition()->getParameters(),
                    'specificity' => $route->getCondition()->getSpecificity(),
                ],
                'priority' => $route->getPriority(),
                'middleware' => $route->getMiddleware(),
                'metadata' => $route->getMetadata(),
            ];
        }, $this->routes);
    }

    /**
     * Import routes from array (useful for testing)
     */
    public function import(array $routeData): void
    {
        foreach ($routeData as $data) {
            // This would require route reconstruction logic
            // Implementation depends on specific needs
        }
    }

    /**
     * Check if route matches type criteria
     */
    private function matchesType(Route $route, string $type): bool
    {
        return match ($type) {
            'wordpress' => $route->isWordPressRoute(),
            'laravel' => !$route->isWordPressRoute(),
            default => false
        };
    }

    /**
     * Check if route supports HTTP method
     */
    private function matchesMethod(Route $route, string $method): bool
    {
        return in_array(strtoupper($method), array_map('strtoupper', $route->getMethods()), true);
    }

    /**
     * Check if route has specific middleware
     */
    private function hasMiddleware(Route $route, string|array $middleware): bool
    {
        $routeMiddleware = $route->getMiddleware();
        
        if (is_string($middleware)) {
            return in_array($middleware, $routeMiddleware, true);
        }
        
        if (is_array($middleware)) {
            return !empty(array_intersect($middleware, $routeMiddleware));
        }
        
        return false;
    }
}