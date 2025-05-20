<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Services;

use Pollora\Route\Domain\Contracts\ConditionValidatorInterface;
use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Domain service for validating WordPress conditional tags.
 * 
 * This class validates if a route matches a specific WordPress condition,
 * with plugin conditions taking priority over native WordPress conditions.
 */
class ConditionValidator implements ConditionValidatorInterface
{
    /**
     * Determine if the route matches a specific WordPress condition.
     *
     * @param RouteEntity $route The route to validate
     * @param mixed $request The request to check against
     * @return bool Whether the condition is met
     */
    public function matches(RouteEntity $route, $request): bool
    {
        // If this isn't a WordPress route or has no condition, nothing to validate
        if (!$route->isWordPressRoute() || !$route->hasCondition()) {
            return true;
        }

        $condition = $route->getCondition();
        $params = $route->getConditionParameters();

        // Check if function exists before trying to call it
        if (!function_exists($condition)) {
            return false;
        }

        // Call the WordPress conditional function with the given parameters
        // Explicitly cast the result to boolean to ensure correct return type
        return (bool)call_user_func_array($condition, $params);
    }
} 