<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\ConditionValidatorInterface;
use Pollora\Route\Domain\Models\RouteCollectionEntity;
use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Application service for matching routes against requests.
 *
 * This service orchestrates the route matching process, including handling
 * WordPress-specific routing scenarios.
 */
class RouteMatchingService
{
    public function __construct(
        private ConditionValidatorInterface $conditionValidator
    ) {}

    /**
     * Find a matching route for a request.
     *
     * @param  mixed  $request  The request to match
     * @param  RouteCollectionEntity  $routeCollection  Collection of routes to check
     * @param  array<string, mixed>  $config  Configuration settings
     * @return RouteEntity|null The matching route or null if none found
     */
    public function findMatchingRoute($request, RouteCollectionEntity $routeCollection, array $config): ?RouteEntity
    {
        // Process WordPress admin requests
        if ($this->isWordPressAdminRequest()) {
            return $this->createAdminRoute($request);
        }

        // Process special WordPress requests
        if ($this->isSpecialWordPressRequest()) {
            $specialRoute = $this->findSpecialWordPressRoute($routeCollection);
            if ($specialRoute !== null) {
                return $specialRoute;
            }

            return $this->createSpecialWordPressRoute($request);
        }

        // First find standard routes that match
        $matchingRoute = $this->findStandardRoute($request, $routeCollection);

        // If we found a WordPress route, check for more specific ones
        if ($matchingRoute !== null && $matchingRoute->isWordPressRoute()) {
            $wpRoutes = $this->getWordPressRoutes($routeCollection);
            $specificRoute = $this->findMostSpecificWordPressRoute($wpRoutes, $config);

            if ($specificRoute !== null) {
                // Add WordPress bindings
                $this->addWordPressBindings($specificRoute);

                return $specificRoute;
            }
        }

        // If no route is found, create a fallback route
        if ($matchingRoute === null) {
            return $this->createFallbackRoute($request);
        }

        return $matchingRoute;
    }

    /**
     * Check if the current request is for the WordPress admin area.
     *
     * @return bool Whether this is an admin request
     */
    private function isWordPressAdminRequest(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    /**
     * Check if the current request is a special WordPress request type.
     *
     * @return bool Whether this is a special request
     */
    private function isSpecialWordPressRequest(): bool
    {
        return (function_exists('is_robots') && is_robots())
            || (function_exists('is_favicon') && is_favicon())
            || (function_exists('is_feed') && is_feed())
            || (function_exists('is_trackback') && is_trackback());
    }

    /**
     * Find a route specifically for handling special WordPress requests.
     *
     * @param  RouteCollectionEntity  $routeCollection  Collection to search in
     * @return RouteEntity|null The matching special route or null
     */
    private function findSpecialWordPressRoute(RouteCollectionEntity $routeCollection): ?RouteEntity
    {
        $specialCondition = null;

        if (function_exists('is_robots') && is_robots()) {
            $specialCondition = 'is_robots';
        } elseif (function_exists('is_favicon') && is_favicon()) {
            $specialCondition = 'is_favicon';
        } elseif (function_exists('is_feed') && is_feed()) {
            $specialCondition = 'is_feed';
        } elseif (function_exists('is_trackback') && is_trackback()) {
            $specialCondition = 'is_trackback';
        }

        if ($specialCondition === null) {
            return null;
        }

        // Look for a route with this specific condition
        foreach ($routeCollection->getRoutes() as $route) {
            if ($route->isWordPressRoute() && $route->getCondition() === $specialCondition) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Create a placeholder for an admin route. In the infrastructure layer, this
     * would be implemented to return an actual Route object.
     *
     * @param  mixed  $request  The current request
     * @return RouteEntity A placeholder admin route
     */
    private function createAdminRoute($request): RouteEntity
    {
        // This will be implemented in the infrastructure layer
        // with the actual WordPress/Laravel-specific implementation
        return new RouteEntity(['GET', 'POST'], 'admin/{any?}', null);
    }

    /**
     * Create a placeholder for a special WordPress route.
     *
     * @param  mixed  $request  The current request
     * @return RouteEntity A placeholder special route
     */
    private function createSpecialWordPressRoute($request): RouteEntity
    {
        // This will be implemented in the infrastructure layer
        return new RouteEntity(['GET'], 'special-wordpress-request', null);
    }

    /**
     * Create a fallback route for when no routes match the request.
     *
     * @param  mixed  $request  The current request
     * @return RouteEntity A placeholder fallback route
     */
    private function createFallbackRoute($request): RouteEntity
    {
        // This will be implemented in the infrastructure layer
        return new RouteEntity(['GET'], '{any}', null);
    }

    /**
     * Find a standard route matching the request.
     *
     * @param  mixed  $request  The request to match
     * @param  RouteCollectionEntity  $routeCollection  Collection to search in
     * @return RouteEntity|null The matching route or null
     */
    private function findStandardRoute($request, RouteCollectionEntity $routeCollection): ?RouteEntity
    {
        // This would be implemented in the infrastructure layer
        // with the actual route matching logic
        return null;
    }

    /**
     * Get all WordPress routes from the collection.
     *
     * @param  RouteCollectionEntity  $routeCollection  Collection to filter
     * @return array<RouteEntity> WordPress routes
     */
    private function getWordPressRoutes(RouteCollectionEntity $routeCollection): array
    {
        $wpRoutes = [];

        foreach ($routeCollection->getRoutes() as $route) {
            if ($route->isWordPressRoute()) {
                $wpRoutes[] = $route;
            }
        }

        // Sort WordPress routes by specificity
        usort($wpRoutes, function ($a, $b) {
            // Routes with parameters should come first
            $aHasParams = ! empty($a->getConditionParameters());
            $bHasParams = ! empty($b->getConditionParameters());

            if ($aHasParams && ! $bHasParams) {
                return -1;
            }

            if (! $aHasParams && $bHasParams) {
                return 1;
            }

            // If both have or don't have parameters, maintain original order
            return 0;
        });

        return $wpRoutes;
    }

    /**
     * Find the most specific WordPress route that matches the current request.
     *
     * @param  array<RouteEntity>  $wpRoutes  Routes to check
     * @param  array<string, mixed>  $config  Configuration settings
     * @return RouteEntity|null The most specific matching route or null
     */
    private function findMostSpecificWordPressRoute(array $wpRoutes, array $config): ?RouteEntity
    {
        $matchingRoutes = [];

        // Check each route against its WordPress condition
        foreach ($wpRoutes as $route) {
            $condition = $route->getCondition();
            if (function_exists($condition)) {
                $params = $route->getConditionParameters();
                if (call_user_func_array($condition, $params)) {
                    // Generate a unique key for this route
                    $uniqueKey = $condition;
                    if (! empty($params)) {
                        $uniqueKey .= ':'.serialize($params);
                    }
                    $matchingRoutes[$uniqueKey] = $route;
                }
            }
        }

        if (empty($matchingRoutes)) {
            return null;
        }

        // Get plugin conditions (higher priority)
        $pluginConditions = [];
        $pluginConditionsConfig = $config['wordpress.plugin_conditions'] ?? [];

        foreach ($pluginConditionsConfig as $pluginName => $pluginConditionGroup) {
            $pluginConditions = array_merge($pluginConditions, array_keys($pluginConditionGroup));
        }

        // Get native WordPress conditions (lower priority)
        $wordpressConditions = array_keys($config['wordpress.conditions'] ?? []);

        // Create hierarchy order
        $hierarchyOrder = array_merge($pluginConditions, $wordpressConditions);
        $hierarchyOrder[] = '__return_true'; // Add fallback condition

        // Find the most specific route
        foreach ($hierarchyOrder as $condition) {
            foreach ($matchingRoutes as $key => $route) {
                if (strpos($key, $condition) === 0) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Add WordPress data bindings to a route.
     *
     * @param  RouteEntity  $route  The route to add bindings to
     */
    private function addWordPressBindings(RouteEntity $route): void
    {
        // This would be implemented in the infrastructure layer
        // to add actual WordPress data to the route parameters
    }
}
