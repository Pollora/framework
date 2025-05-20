<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Interface for validating WordPress conditions.
 * 
 * Defines the contract for components that validate WordPress conditional tags
 * to determine if a route matches a specific WordPress condition.
 */
interface ConditionValidatorInterface
{
    /**
     * Determine if the route matches a specific WordPress condition.
     *
     * @param RouteEntity $route The route to validate
     * @param mixed $request The request to check against
     * @return bool Whether the condition is met
     */
    public function matches(RouteEntity $route, $request): bool;
} 