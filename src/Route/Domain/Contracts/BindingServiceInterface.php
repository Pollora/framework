<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Interface for route binding services.
 *
 * Defines methods for adding global WordPress objects to route parameters.
 */
interface BindingServiceInterface
{
    /**
     * Add WordPress objects to route parameters.
     *
     * @param RouteEntity $route The route to add bindings to
     * @return RouteEntity The route with added bindings
     */
    public function addBindings(RouteEntity $route): RouteEntity;
    
    /**
     * Check if bindings should be added to this route.
     *
     * @param RouteEntity $route The route to check
     * @return bool True if bindings should be added
     */
    public function shouldAddBindings(RouteEntity $route): bool;
} 