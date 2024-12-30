<?php

declare(strict_types=1);

namespace Pollora\Route\Matching;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\ValidatorInterface;
use Illuminate\Routing\Route;

/**
 * Validator for WordPress-specific route conditions.
 *
 * This validator implements Laravel's ValidatorInterface to check if a route
 * matches based on WordPress conditional functions (like is_single(), is_page(), etc.).
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
     * @param Route $route The route to validate
     * @param Request $request The current HTTP request
     * @return bool True if the WordPress condition is met, false otherwise
     *
     * @example
     * // For a route with condition 'is_single' and parameter [123]
     * // This will effectively call: is_single(123)
     */
    public function matches(Route $route, Request $request): bool
    {
        $condition = $route->getCondition();

        return function_exists($condition) && call_user_func_array($condition, $route->getConditionParameters());
    }
}
