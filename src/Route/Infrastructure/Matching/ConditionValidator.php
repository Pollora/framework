<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Matching;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\ValidatorInterface;
use Pollora\Route\Infrastructure\Adapters\Route;

/**
 * Implementation of the condition validator.
 *
 * This class validates if a route matches a specific WordPress condition,
 * with plugin conditions taking priority over native WordPress conditions.
 */
class ConditionValidator implements ValidatorInterface
{
    /**
     * Determine if the route matches the given request based on WordPress conditions.
     *
     * @param  \Illuminate\Routing\Route  $route  Route to validate
     * @param  Request  $request  Request to check against
     * @return bool True if the route matches the request, false otherwise
     */
    public function matches($route, $request): bool
    {
        // If this isn't a WordPress route or has no condition, nothing to validate
        if (! $route instanceof Route || ! $route->isWordPressRoute() || ! $route->hasCondition()) {
            return true;
        }

        $condition = $route->getCondition();
        $params = $route->getConditionParameters();

        // Check if function exists before trying to call it
        if (! function_exists($condition)) {
            return false;
        }

        // Call the WordPress conditional function with the given parameters
        // Explicitly cast the result to boolean to ensure correct return type
        return (bool) call_user_func_array($condition, $params);
    }
}
