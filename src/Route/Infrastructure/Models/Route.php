<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Models;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Contracts\WordPressRouteInterface;

/**
 * Extended Route class with WordPress condition support.
 *
 * This class extends Laravel's Route to add WordPress conditional tag support
 * while maintaining full compatibility with Laravel's routing system.
 *
 * WordPress conditions are evaluated during route matching: if the condition
 * function (e.g. is_single(), is_page()) returns true, the route matches.
 * WordPress must be bootstrapped before route matching occurs (handled by QueryTrait).
 */
class Route extends IlluminateRoute implements WordPressRouteInterface
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
     * Set whether this is a WordPress route.\n     */
    public function setIsWordPressRoute(bool $isWordPressRoute): static
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
     * Set the WordPress condition.\n     */
    public function setCondition(string $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Get the resolved WordPress condition function name.
     *
     * Resolves condition aliases (e.g., 'single' → 'is_single') through the
     * injected condition resolver. Returns the raw condition if no resolver is set.
     */
    public function getCondition(): string
    {
        if ($this->conditionResolver instanceof ConditionResolverInterface) {
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
     * @param  array<mixed>  $parameters\n     */
    public function setConditionParameters(array $parameters): static
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
     * Set the condition resolver instance.\n     */
    public function setConditionResolver(ConditionResolverInterface $resolver): static
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

        // WordPress routes match based on WordPress conditional tags
        if ($this->isWordPressRoute() && $this->hasCondition()) {
            return $this->matchesWordPressCondition();
        }

        // Standard Laravel routes use URI pattern matching
        return parent::matches($request, $includingMethod);
    }

    /**
     * Check if the WordPress condition matches the current request.
     *
     * Evaluates the WordPress conditional function (e.g. is_single(), is_page())
     * with any configured parameters. WordPress must be bootstrapped before this
     * is called (ensured by QueryTrait::runWp() in the request lifecycle).
     */
    protected function matchesWordPressCondition(): bool
    {
        $condition = $this->getCondition();
        $parameters = $this->getConditionParameters();

        if (! function_exists($condition)) {
            return false;
        }

        return (bool) call_user_func_array($condition, $parameters);
    }
}
