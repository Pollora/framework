<?php

declare(strict_types=1);

namespace Pollora\Route\Matching;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\ValidatorInterface;
use Illuminate\Routing\Route;

/**
 * WordPress condition validator for routes.
 *
 * This validator checks if a route matches based on WordPress conditional functions
 * such as is_page(), is_single(), etc.
 */
class ConditionValidator implements ValidatorInterface
{
    /**
     * Determine if the route matches the given request based on WordPress conditions.
     *
     * Checks if:
     * 1. The condition function exists in WordPress
     * 2. The condition function returns true when called with the route parameters
     *
     * @param  Route  $route  The route to validate
     * @param  Request  $request  The current HTTP request
     * @return bool True if the WordPress condition is met, false otherwise
     *
     * @example
     * // For a route with condition 'is_single' and parameter [123]
     * // This will effectively call: is_single(123)
     */
    public function matches(Route $route, Request $request): bool
    {
        // Get the WordPress condition from the route
        $condition = $route->getCondition();

        // Check if the condition function exists
        if (! function_exists($condition)) {
            return false;
        }

        // Get the parameters for the condition
        $parameters = $route->getConditionParameters();

        // Call the WordPress condition function with the parameters
        $result = call_user_func_array($condition, $parameters);

        // Convert the result to a boolean
        return (bool) $result;
    }
}
