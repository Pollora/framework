<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Interface for route matchers.
 *
 * Defines the contract for components that match routes against requests.
 */
interface RouteMatcherInterface
{
    /**
     * Determine if the route matches a given request.
     *
     * @param  RouteEntity  $route  The route to check
     * @param  mixed  $request  The request to match against
     * @return bool Whether the route matches the request
     */
    public function matches(RouteEntity $route, $request): bool;
}
