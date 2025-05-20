<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Illuminate\Support\Facades\Route as RouteFacade;
use Pollora\Route\Domain\Contracts\RouteRegistrarInterface;
use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Laravel implementation of the route registrar.
 *
 * This class adapts the domain route registrar interface to Laravel's route registration.
 */
class LaravelRouteRegistrar implements RouteRegistrarInterface
{
    /**
     * @param LaravelRoute $routeAdapter
     */
    public function __construct(
        private LaravelRoute $routeAdapter
    ) {}

    /**
     * Register a new route.
     *
     * @param array<int, string> $methods HTTP methods
     * @param string $uri URI pattern
     * @param mixed $action Route action
     * @return RouteEntity The created route entity
     */
    public function register(array $methods, string $uri, $action): RouteEntity
    {
        // Use Laravel's route registration
        $laravelRoute = RouteFacade::match($methods, $uri, $action);

        // Convert to domain entity
        return $this->routeAdapter->toDomainEntity($laravelRoute);
    }

    /**
     * Register a WordPress-specific route.
     *
     * @param array<int, string> $methods HTTP methods
     * @param string $condition WordPress condition
     * @param array<mixed> $parameters Condition parameters
     * @param mixed $action Route action
     * @return RouteEntity The created WordPress route entity
     */
    public function registerWordPressRoute(array $methods, string $condition, array $parameters, $action): RouteEntity
    {
        // Prepare arguments for wpMatch macro
        $args = array_merge([$condition], $parameters, [$action]);

        // Laravel's wpMatch macro handles WordPress route registration
        $laravelRoute = RouteFacade::wpMatch($methods, ...$args);

        // Convert to domain entity
        return $this->routeAdapter->toDomainEntity($laravelRoute);
    }
}
