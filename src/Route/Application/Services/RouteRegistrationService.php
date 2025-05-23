<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\RouteRegistrarInterface;
use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Application service for registering routes.
 *
 * This service handles the registration of routes, including WordPress-specific routes
 * with conditions.
 */
class RouteRegistrationService
{
    public function __construct(
        private RouteRegistrarInterface $routeRegistrar
    ) {}

    /**
     * Register a new route with the given HTTP methods, URI, and action.
     *
     * @param  array<int, string>  $methods  HTTP methods
     * @param  string  $uri  URI pattern
     * @param  mixed  $action  Route action
     * @return RouteEntity The registered route
     */
    public function registerRoute(array $methods, string $uri, $action): RouteEntity
    {
        return $this->routeRegistrar->register($methods, $uri, $action);
    }

    /**
     * Register a new WordPress route with a condition.
     *
     * @param  array<int, string>  $methods  HTTP methods
     * @param  string  $condition  WordPress condition
     * @param  mixed  $action  Route action
     * @param  array<mixed>  $conditionParams  Parameters for the condition
     * @return RouteEntity The registered WordPress route
     */
    public function registerWordPressRoute(array $methods, string $condition, $action, array $conditionParams = []): RouteEntity
    {
        // Create a unique URI that incorporates both the condition and parameters
        $uri = $condition;

        // Only modify URI if we have parameters
        if (! empty($conditionParams)) {
            // Create a unique suffix based on the parameters
            $paramSuffix = '_'.md5(serialize($conditionParams));
            $uri = $condition.$paramSuffix;
        }

        // Register the WordPress route
        $route = $this->routeRegistrar->registerWordPressRoute($methods, $condition, $conditionParams, $action);

        // Apply standard WordPress middleware
        $this->applyWordPressMiddleware($route);

        return $route;
    }

    /**
     * Apply WordPress-specific middleware to a route.
     *
     * @param  RouteEntity  $route  The route to apply middleware to
     */
    private function applyWordPressMiddleware(RouteEntity $route): void
    {
        // In the domain, we only mark that middleware should be applied
        // The actual middleware application happens in the infrastructure layer
    }
}
