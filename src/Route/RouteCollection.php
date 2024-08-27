<?php

declare(strict_types=1);

namespace Pollen\Route;

use Illuminate\Routing\RouteCollection as IlluminateRouteCollection;

class RouteCollection extends IlluminateRouteCollection
{
    protected function addToCollections($route)
    {
        $domainAndUri = $this->buildDomainAndUri($route);

        foreach ($route->methods() as $method) {
            $this->routes[$method][$domainAndUri] = $route;
            $this->allRoutes[$method.$domainAndUri] = $route;
        }
    }

    private function buildDomainAndUri($route): string
    {
        $domainAndUri = $route->getDomain().$route->uri();

        if ($route->hasCondition() && ! empty($route->getConditionParameters())) {
            $domainAndUri .= serialize($route->getConditionParameters());
        }

        return $domainAndUri;
    }
}
