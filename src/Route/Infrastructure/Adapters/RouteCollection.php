<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Illuminate\Routing\RouteCollection as IlluminateRouteCollection;

/**
 * Extended RouteCollection that handles WordPress-specific route collection functionality.
 *
 * This class extends Laravel's RouteCollection to provide additional handling for
 * WordPress conditions and parameters in route collection management.
 */
class RouteCollection extends IlluminateRouteCollection
{
    /**
     * Add a route to the appropriate collections.
     *
     * Extends the base collection functionality to handle WordPress condition parameters
     * when building the route key.
     *
     * @param  Route  $route  The route instance to add to collections
     * @return void
     */
    protected function addToCollections($route)
    {
        $domainAndUri = $this->buildDomainAndUri($route);

        foreach ($route->methods() as $method) {
            $this->routes[$method][$domainAndUri] = $route;
            $this->allRoutes[$method.$domainAndUri] = $route;
        }
    }

    /**
     * Build a unique identifier for the route based on domain, URI, and condition parameters.
     *
     * Creates a unique string that combines:
     * - The route's domain
     * - The route's URI
     * - Serialized condition parameters (if present)
     *
     * @param  Route  $route  The route instance to build the identifier for
     * @return string The unique route identifier
     */
    private function buildDomainAndUri($route): string
    {
        $domainAndUri = $route->getDomain().$route->uri();

        if ($route->hasCondition() && ! empty($route->getConditionParameters())) {
            $domainAndUri .= serialize($route->getConditionParameters());
        }

        return $domainAndUri;
    }
}
