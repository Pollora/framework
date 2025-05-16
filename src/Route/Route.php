<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\MethodValidator;
use Illuminate\Routing\Route as IlluminateRoute;
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
     * Flag indicating if this route was created using the wordpress macro.
     *
     * @var bool True if created with Route::wordpress(), false otherwise
     */
    protected bool $isWordPressRoute = false;

    /**
     * Initialize the parameters array if it doesn't exist yet.
     * This prevents "Route is not bound" errors when accessing parameters.
     *
     * @return array The initialized parameters array
     */
    protected function initializeParameters(): array
    {
        if ($this->parameters === null) {
            $this->parameters = [];
        }

        return $this->parameters;
    }

    /**
     * Set a parameter to the given value.
     *
     * @param  string  $name
     * @param  string|object|null  $value
     */
    public function setParameter($name, $value): void
    {
        // Initialize parameters if they don't exist yet
        $this->initializeParameters();

        $this->parameters[$name] = $value;
    }

    /**
     * Get the key / value list of parameters for the route.
     */
    public function parameters(): array
    {
        // Initialize parameters if they don't exist yet
        return $this->initializeParameters();
    }

    /**
     * Determine if the route matches the given request.
     *
     * @param  Request  $request  The HTTP request to match against
     * @param  bool  $includingMethod  Whether to include HTTP method validation
     * @return bool True if the route matches the request, false otherwise
     */
    public function matches(Request $request, $includingMethod = true): bool
    {
        $this->compileRoute();

        // Only process WordPress conditions if this is a WordPress route
        if ($this->isWordPressRoute && $this->hasCondition()) {
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
     * @param  array<string, string|array>  $conditions  Mapping of condition signatures to their routes
     * @return self Returns the current instance for method chaining
     */
    public function setConditions(array $conditions = []): self
    {
        $this->conditions = $conditions;

        // Only parse conditions if this is a WordPress route
        if ($this->isWordPressRoute) {
            $this->condition = $this->parseCondition($this->uri());
        }

        return $this;
    }

    /**
     * Mark this route as a WordPress route.
     *
     * @param  bool  $isWordPressRoute  Whether this is a WordPress route
     * @return self Returns the current instance for method chaining
     */
    public function setIsWordPressRoute(bool $isWordPressRoute = true): self
    {
        $this->isWordPressRoute = $isWordPressRoute;

        // If we're setting this as a WordPress route, parse the condition
        if ($isWordPressRoute && $this->conditions !== []) {
            $this->condition = $this->parseCondition($this->uri());
        }

        return $this;
    }

    /**
     * Check if this route was created using the wordpress macro.
     *
     * @return bool True if this is a WordPress route, false otherwise
     */
    public function isWordPressRoute(): bool
    {
        return $this->isWordPressRoute;
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
        return $this->conditionParams ?? [];
    }

    /**
     * Set parameters for the current condition.
     *
     * @param  array<mixed>  $parameters  The parameters to set for the condition
     * @return self Returns the current instance for method chaining
     */
    public function setConditionParameters(array $parameters = []): self
    {
        $this->conditionParams = $parameters;

        return $this;
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
     * @param  string  $condition  The condition string to parse
     * @return string The matching condition signature or empty string if not found
     */
    protected function parseCondition(string $condition): string
    {
        // First check in all conditions (includes plugin conditions)
        foreach ($this->getConditions() as $signature => $conds) {
            $conds = (array) $conds;
            if (in_array($condition, $conds, true)) {
                return $signature;
            }
        }

        return $condition;
    }

    /**
     * Parse condition parameters from route action.
     *
     * @deprecated This method is deprecated and will be removed in a future version.
     * Use setConditionParameters() instead.
     *
     * @param  array<string, mixed>  $action  The route action array
     * @return array<mixed> Extracted condition parameters
     */
    protected function parseConditionParams(array $action): array
    {
        if ($this->condition === '' || $this->condition === '0') {
            return [];
        }

        // Get all numeric keys from the action array
        $numericKeys = array_filter(array_keys($action), 'is_numeric');

        // If there are no numeric keys, return empty array
        if ($numericKeys === []) {
            return [];
        }

        // Sort the keys to ensure they're in order
        sort($numericKeys);

        // Get all parameters except the first (condition) and the last (callback)
        $paramKeys = array_slice($numericKeys, 1, count($numericKeys) - 2);

        // If there are no parameters, return empty array
        if ($paramKeys === []) {
            return [];
        }

        // Extract the parameters
        $params = [];
        foreach ($paramKeys as $key) {
            $params[] = $action[$key];
        }

        return $params;
    }

    /**
     * Check if route matches WordPress conditions.
     *
     * Validates the route against all registered WordPress validators.
     *
     * @param  Request  $request  The request to validate against
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
     * @param  Request  $request  The request to validate against
     * @param  bool  $includingMethod  Whether to include HTTP method validation
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

    /**
     * Check if the current condition is a plugin condition.
     *
     * @return bool True if this is a plugin condition, false otherwise
     */
    public function isPluginCondition(): bool
    {
        if (! $this->hasCondition()) {
            return false;
        }

        $condition = $this->getCondition();
        $app = $this->container->get('app');

        if (! method_exists($app, 'make')) {
            return false;
        }

        $config = $app->make('config');
        $pluginConditionsConfig = $config->get('wordpress.plugin_conditions', []);

        foreach ($pluginConditionsConfig as $pluginConditions) {
            if (array_key_exists($condition, $pluginConditions)) {
                return true;
            }
        }

        return false;
    }
}
