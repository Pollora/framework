<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;

/**
 * Extended Route class with WordPress condition support.
 *
 * This class extends Laravel's Route to add WordPress conditional tag support
 * while maintaining full compatibility with Laravel's routing system.
 */
class Route extends IlluminateRoute
{
    /**
     * Whether this is a WordPress route.
     */
    protected bool $isWordPressRoute = false;

    /**
     * WordPress condition function name.
     */
    protected string $condition = '';

    /**
     * Parameters for the WordPress condition.
     *
     * @var array<mixed>
     */
    protected array $conditionParameters = [];

    /**
     * Set whether this is a WordPress route.
     *
     * @return $this
     */
    public function setIsWordPressRoute(bool $isWordPressRoute): self
    {
        $this->isWordPressRoute = $isWordPressRoute;

        return $this;
    }

    /**
     * Check if this is a WordPress route.
     */
    public function isWordPressRoute(): bool
    {
        return $this->isWordPressRoute;
    }

    /**
     * Set the WordPress condition.
     *
     * @return $this
     */
    public function setCondition(string $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Get the WordPress condition.
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Check if route has a WordPress condition.
     */
    public function hasCondition(): bool
    {
        return ! empty($this->condition);
    }

    /**
     * Set the condition parameters.
     *
     * @param  array<mixed>  $parameters
     * @return $this
     */
    public function setConditionParameters(array $parameters): self
    {
        $this->conditionParameters = $parameters;

        return $this;
    }

    /**
     * Get the condition parameters.
     *
     * @return array<mixed>
     */
    public function getConditionParameters(): array
    {
        return $this->conditionParameters;
    }

    /**
     * Determine if the route matches given request.
     *
     * @param  bool  $includingMethod
     */
    public function matches(Request $request, $includingMethod = true): bool
    {
        $this->compileRoute();

        // If this is a WordPress route, check the condition
        if ($this->isWordPressRoute() && $this->hasCondition()) {
            return $this->matchesWordPressCondition();
        }

        // Otherwise, use Laravel's default matching
        return parent::matches($request, $includingMethod);
    }

    /**
     * Check if the WordPress condition matches.
     */
    protected function matchesWordPressCondition(): bool
    {
        // Ensure WordPress has parsed the request before evaluating conditions
        $this->ensureWordPressQueryParsed();

        $condition = $this->getCondition();
        $parameters = $this->getConditionParameters();

        // Check if the WordPress function exists and call it
        if (function_exists($condition)) {
            return call_user_func_array($condition, $parameters);
        }

        return false;
    }

    /**
     * Ensure WordPress has parsed the current request.
     */
    protected function ensureWordPressQueryParsed(): void
    {
        global $wp, $wp_query;

        // If WordPress hasn't parsed the request yet, do it now
        if (function_exists('wp') && isset($wp) && ! $wp->did_permalink && ! $wp_query->is_main_query()) {
            // Parse the current request URL to set up WordPress query vars
            if (function_exists('wp_parse_request')) {
                wp_parse_request();
            } elseif (method_exists($wp, 'parse_request')) {
                $wp->parse_request();
            }
        }
    }
}
