<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\BindingServiceInterface;
use Pollora\Route\Domain\Models\NullablePostEntity;
use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Service for binding WordPress objects to route parameters.
 */
class RouteBindingService implements BindingServiceInterface
{
    /**
     * Add WordPress objects to route parameters.
     *
     * @param  RouteEntity  $route  The route to add bindings to
     * @return RouteEntity The route with added bindings
     */
    public function addBindings(RouteEntity $route): RouteEntity
    {
        // Get the global WordPress objects
        $post = $this->getGlobalPost();
        $wpQuery = $this->getGlobalWpQuery();

        // Add them to route parameters
        $parameters = $route->getParameters();
        $parameters['post'] = $post;
        $parameters['wp_query'] = $wpQuery;

        return $route->setParameters($parameters);
    }

    /**
     * Check if bindings should be added to this route.
     *
     * @param  RouteEntity  $route  The route to check
     * @return bool True if bindings should be added
     */
    public function shouldAddBindings(RouteEntity $route): bool
    {
        return $route->hasCondition();
    }

    /**
     * Get the global WordPress post object.
     *
     * @return object The WordPress post or a nullable post entity
     */
    protected function getGlobalPost(): object
    {
        global $post;

        return $post ?? new NullablePostEntity;
    }

    /**
     * Get the global WordPress query object.
     *
     * @return object|null The WordPress query object or null
     */
    protected function getGlobalWpQuery(): ?object
    {
        global $wp_query;

        return $wp_query ?? null;
    }
}
