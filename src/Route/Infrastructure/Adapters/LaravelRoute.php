<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Illuminate\Routing\Route as IlluminateRoute;
use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Laravel adapter for Route entities.
 *
 * This class provides bidirectional conversion between domain RouteEntity
 * objects and Laravel/Illuminate Route objects.
 */
class LaravelRoute
{
    /**
     * Convert a domain RouteEntity to a framework Route.
     *
     * @param  RouteEntity  $routeEntity  Domain route entity
     * @param  Route  $routePrototype  Laravel route prototype to populate
     * @return Route Framework route instance
     */
    public function toFrameworkRoute(RouteEntity $routeEntity, Route $routePrototype): Route
    {
        // Set basic properties
        $routePrototype->setIsWordPressRoute($routeEntity->isWordPressRoute());

        if ($routeEntity->hasCondition()) {
            $routePrototype->setCondition($routeEntity->getCondition());
            $routePrototype->setConditionParameters($routeEntity->getConditionParameters());
        }

        // Set parameters if any exist
        $params = $routeEntity->getParameters();
        if (! empty($params)) {
            foreach ($params as $key => $value) {
                $routePrototype->setParameter($key, $value);
            }
        }

        return $routePrototype;
    }

    /**
     * Convert a framework Route to a domain RouteEntity.
     *
     * @param  IlluminateRoute  $route  Framework route
     * @return RouteEntity Domain route entity
     */
    public function toDomainEntity(IlluminateRoute $route): RouteEntity
    {
        $methods = $route->methods();
        $uri = $route->uri();
        $action = $route->getAction();

        $entity = new RouteEntity($methods, $uri, $action);

        // Set domain if present
        $domain = $route->getDomain();
        if ($domain !== null) {
            $entity->setDomain($domain);
        }

        // Set parameters if present
        $parameters = [];
        if ($route instanceof Route) {
            $parameters = $route->parameters();

            // Set WordPress-specific properties
            $entity->setIsWordPressRoute($route->isWordPressRoute());

            if ($route->hasCondition()) {
                $entity->setCondition($route->getCondition());
                $entity->setConditionParameters($route->getConditionParameters());
            }
        }

        if (! empty($parameters)) {
            $entity->setParameters($parameters);
        }

        return $entity;
    }
}
