<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Interface for route registrars.
 *
 * Defines the contract for components that register routes in the system.
 */
interface RouteRegistrarInterface
{
    /**
     * Register a new route.
     *
     * @param  array<int, string>  $methods  HTTP methods
     * @param  string  $uri  URI pattern
     * @param  mixed  $action  Route action
     * @return RouteEntity The created route entity
     */
    public function register(array $methods, string $uri, $action): RouteEntity;

    /**
     * Register a WordPress-specific route.
     *
     * @param  array<int, string>  $methods  HTTP methods
     * @param  string  $condition  WordPress condition
     * @param  array<mixed>  $parameters  Condition parameters
     * @param  mixed  $action  Route action
     * @return RouteEntity The created WordPress route entity
     */
    public function registerWordPressRoute(array $methods, string $condition, array $parameters, $action): RouteEntity;
}
