<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;

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
     * Condition resolver instance for resolving condition aliases.
     */
    protected ?ConditionResolverInterface $conditionResolver = null;

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
     * Get the resolved WordPress condition function name.
     *
     * This method returns the WordPress condition function name, resolving
     * any condition aliases through the injected condition resolver if available.
     * If no resolver is available, returns the raw condition string.
     *
     * The method supports both condition aliases (e.g., 'single', 'page') and
     * direct WordPress function names (e.g., 'is_single', 'is_page'). When a
     * condition resolver is available, aliases are automatically resolved to
     * their corresponding WordPress function names.
     *
     * @return string The resolved WordPress condition function name
     *
     * @example
     * // With resolver available:
     * $route->setCondition('single');
     * $route->getCondition(); // Returns 'is_single'
     *
     * // Without resolver or direct function name:
     * $route->setCondition('is_single');
     * $route->getCondition(); // Returns 'is_single'
     */
    public function getCondition(): string
    {
        if ($this->conditionResolver instanceof \Pollora\Route\Domain\Contracts\ConditionResolverInterface) {
            return $this->conditionResolver->resolveCondition($this->condition);
        }

        return $this->condition;
    }

    /**
     * Check if route has a WordPress condition.
     */
    public function hasCondition(): bool
    {
        return $this->condition !== '' && $this->condition !== '0';
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
     * Set the condition resolver instance.
     *
     * This method allows injection of a condition resolver that can translate
     * condition aliases to actual WordPress function names. The resolver is
     * used by the getCondition method to provide resolved condition names.
     *
     * @param  ConditionResolverInterface  $resolver  The condition resolver instance
     * @return $this
     */
    public function setConditionResolver(ConditionResolverInterface $resolver): self
    {
        $this->conditionResolver = $resolver;

        return $this;
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
