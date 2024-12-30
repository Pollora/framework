<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\MethodValidator;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Arr;
use Pollora\Route\Matching\ConditionValidator;

/**
 * Extended Route class that adds WordPress-specific routing capabilities.
 *
 * This class extends Laravel's base Route to provide WordPress condition handling
 * and custom validation for WordPress-specific routing scenarios.
 */
class Route extends IlluminateRoute
{
    /**
     * Array of registered WordPress conditions.
     *
     * @var array<string, string|array> Mapping of condition signatures to their corresponding routes
     */
    protected array $conditions = [];

    /**
     * Current WordPress condition signature.
     *
     * @var string The active condition signature for this route
     */
    protected string $condition = '';

    /**
     * Parameters for the current WordPress condition.
     *
     * @var array<mixed> Parameters extracted from the route action
     */
    protected array $conditionParams = [];

    /**
     * WordPress-specific route validators.
     *
     * @var array<ConditionValidator>|null Collection of WordPress condition validators
     */
    protected ?array $wordpressValidators = null;

    /**
     * Determine if the route matches the given request.
     *
     * @param Request $request The HTTP request to match against
     * @param bool $includingMethod Whether to include HTTP method validation
     * @return bool True if the route matches the request, false otherwise
     */
    public function matches(Request $request, $includingMethod = true): bool
    {
        $this->compileRoute();

        if ($this->hasCondition()) {
            return $this->matchesWordPressConditions($request);
        }

        return $this->matchesIlluminateValidators($request, $includingMethod);
    }

    /**
     * Check if the route has a WordPress condition.
     *
     * @return bool True if a valid condition is set, false otherwise
     */
    public function hasCondition(): bool
    {
        return $this->condition !== '' && $this->condition !== '0';
    }

    /**
     * Set WordPress conditions for the route.
     *
     * @param array<string, string|array> $conditions Mapping of condition signatures to their routes
     * @return self Returns the current instance for method chaining
     */
    public function setConditions(array $conditions = []): self
    {
        $this->conditions = $conditions;
        $this->condition = $this->parseCondition($this->uri());
        $this->conditionParams = $this->parseConditionParams($this->getAction());

        return $this;
    }

    /**
     * Get the current WordPress condition signature.
     *
     * @return string The current condition signature
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Get all registered WordPress conditions.
     *
     * @return array<string, string|array> All registered condition mappings
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Get parameters for the current condition.
     *
     * @return array<mixed> The current condition parameters
     */
    public function getConditionParameters(): array
    {
        return $this->conditionParams;
    }

    /**
     * Get WordPress-specific route validators.
     *
     * Lazily initializes validators if they haven't been set.
     *
     * @return array<ConditionValidator> Array of WordPress condition validators
     */
    public function getWordPressValidators(): array
    {
        return $this->wordpressValidators ??= [new ConditionValidator];
    }

    /**
     * Parse a condition string to get its signature.
     *
     * @param string $condition The condition string to parse
     * @return string The matching condition signature or empty string if not found
     */
    protected function parseCondition(string $condition): string
    {
        foreach ($this->getConditions() as $signature => $conds) {
            $conds = (array) $conds;
            if (in_array($condition, $conds, true)) {
                return $signature;
            }
        }

        return '';
    }

    /**
     * Parse condition parameters from route action.
     *
     * @param array<string, mixed> $action The route action array
     * @return array<mixed> Extracted condition parameters
     */
    protected function parseConditionParams(array $action): array
    {
        if ($this->condition === '' || $this->condition === '0') {
            return [];
        }

        $params = Arr::first($action, fn ($value, $key): bool => is_numeric($key));

        return [$params];
    }

    /**
     * Check if route matches WordPress conditions.
     *
     * Validates the route against all registered WordPress validators.
     *
     * @param Request $request The request to validate against
     * @return bool True if all WordPress conditions match, false otherwise
     */
    private function matchesWordPressConditions(Request $request): bool
    {
        foreach ($this->getWordPressValidators() as $validator) {
            if (! $validator->matches($this, $request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if route matches Laravel validators.
     *
     * Validates the route against standard Laravel route validators.
     *
     * @param Request $request The request to validate against
     * @param bool $includingMethod Whether to include HTTP method validation
     * @return bool True if all Laravel validators match, false otherwise
     */
    private function matchesIlluminateValidators(Request $request, bool $includingMethod): bool
    {
        foreach ($this->getValidators() as $validator) {
            if (! $includingMethod && $validator instanceof MethodValidator) {
                continue;
            }
            if (! $validator->matches($this, $request)) {
                return false;
            }
        }

        return true;
    }
}
