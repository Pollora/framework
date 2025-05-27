<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\WordPress;

use Pollora\Route\Domain\Contracts\ConditionResolverInterface;

/**
 * WordPress conditional tags resolver
 *
 * Resolves WordPress conditional tags and custom conditions for route matching.
 * Supports aliases and parameter validation.
 */
final class ConditionalTagsResolver implements ConditionResolverInterface
{
    private array $customConditions = [];
    private array $aliases = [];

    public function __construct(array $config = [])
    {
        $this->loadConfiguration($config);
    }

    /**
     * Resolve a condition with optional parameters
     */
    public function resolve(string $condition, array $parameters = []): bool
    {
        // Resolve alias first
        $resolvedCondition = $this->resolveAlias($condition);

        // Check custom conditions first
        if (isset($this->customConditions[$resolvedCondition])) {
            return $this->evaluateCustomCondition($resolvedCondition, $parameters);
        }

        // Check WordPress native functions
        if (function_exists($resolvedCondition)) {
            return $this->evaluateWordPressFunction($resolvedCondition, $parameters);
        }

        return false;
    }

    /**
     * Get all available conditions
     */
    public function getAvailableConditions(): array
    {
        $wpConditions = $this->getWordPressConditions();
        $customConditions = array_keys($this->customConditions);
        $aliases = array_keys($this->aliases);

        return array_unique(array_merge($wpConditions, $customConditions, $aliases));
    }

    /**
     * Register a custom condition handler
     */
    public function registerCondition(string $name, callable $handler): void
    {
        $this->customConditions[$name] = $handler;
    }

    /**
     * Check if a condition is available
     */
    public function hasCondition(string $condition): bool
    {
        $resolvedCondition = $this->resolveAlias($condition);

        return isset($this->customConditions[$resolvedCondition])
            || function_exists($resolvedCondition);
    }

    /**
     * Resolve an alias to the actual condition name
     *
     * @param string $alias The alias or condition to resolve
     * @return string The resolved WordPress condition function name
     */
    public function resolveAlias(string $alias): string
    {
        // Normalize alias (trim, lowercase)
        $normalizedAlias = strtolower(trim($alias));

        // If already a WordPress function (starts with is_), return as-is
        if (function_exists($alias)) {
            return $alias;
        }

        // Search through configuration aliases
        if (isset($this->aliases[$normalizedAlias])) {
            return $this->aliases[$normalizedAlias];
        }

        // Also check case-insensitive
        foreach ($this->aliases as $aliasKey => $condition) {
            $aliasKey = (string) $aliasKey;

            if (strtolower(trim($aliasKey)) === $normalizedAlias) {
                return $condition;
            }
        }

        // Check if it's a WordPress function without is_ prefix
        $withPrefix = 'is_' . $alias;
        if (function_exists($withPrefix)) {
            return $withPrefix;
        }

        return $alias;
    }

    /**
     * Register condition aliases
     */
    public function registerAliases(array $aliases): void
    {
        foreach ($aliases as $condition => $aliasConfig) {
            if (is_array($aliasConfig)) {
                // Multiple aliases for one condition
                foreach ($aliasConfig as $alias) {
                    $this->aliases[$alias] = $condition;
                }
            } else {
                // Single alias for condition
                $this->aliases[(string)$aliasConfig] = $condition;
            }
        }
    }

    /**
     * Check if an alias can be resolved to a valid WordPress condition
     *
     * @param string $alias The alias to validate
     * @return bool True if the alias can be resolved to a valid condition
     */
    public function isValidAlias(string $alias): bool
    {
        $resolved = $this->resolveAlias($alias);

        // Check if it resolved to a different condition (meaning it's an alias)
        if ($resolved !== $alias) {
            return $this->hasCondition($resolved);
        }

        // Check if it's a direct WordPress function
        return function_exists($alias) || isset($this->customConditions[$alias]);
    }

    /**
     * Validate condition parameters
     */
    public function validateParameters(string $condition, array $parameters): bool
    {
        $resolvedCondition = $this->resolveAlias($condition);

        // Get parameter requirements for WordPress functions
        $requirements = $this->getParameterRequirements($resolvedCondition);

        if ($requirements === null) {
            // Unknown function, assume valid
            return true;
        }

        // Empty requirements means no parameters allowed
        if (empty($requirements)) {
            return empty($parameters);
        }

        // Validate each parameter type
        foreach ($parameters as $parameter) {
            if (!$this->isValidParameterType($parameter, $requirements)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get WordPress conditional functions
     */
    public function getWordPressConditions(): array
    {
        $conditions = [];

        // Get all functions that start with 'is_'
        $functions = get_defined_functions()['user'] ?? [];

        foreach ($functions as $function) {
            if (str_starts_with($function, 'is_')) {
                $conditions[] = $function;
            }
        }

        // Add common WordPress conditionals that might not be loaded yet
        $commonConditions = [
            'is_404', 'is_admin', 'is_archive', 'is_attachment', 'is_author',
            'is_category', 'is_comment_feed', 'is_date', 'is_day', 'is_embed',
            'is_feed', 'is_front_page', 'is_home', 'is_month', 'is_page',
            'is_paged', 'is_page_template', 'is_preview', 'is_robots',
            'is_search', 'is_single', 'is_singular', 'is_sticky', 'is_tag',
            'is_tax', 'is_taxonomy_hierarchical', 'is_time', 'is_trackback',
            'is_year', 'is_main_query', 'is_favicon'
        ];

        return array_unique(array_merge($conditions, $commonConditions));
    }

    /**
     * Load configuration merging conditions and plugin_conditions
     */
    private function loadConfiguration(array $config): void
    {
        // Merge base conditions and plugin conditions
        $allConditions = $config['conditions'] ?? [];

        // Merge plugin conditions
        if (isset($config['plugin_conditions'])) {
            foreach ($config['plugin_conditions'] as $pluginConditions) {
                $allConditions = array_merge($allConditions, $pluginConditions);
            }
        }

        // Load merged aliases from config
        $this->registerAliases($allConditions);

        // Load custom conditions from config
        if (isset($config['custom_conditions'])) {
            foreach ($config['custom_conditions'] as $name => $handler) {
                if (is_callable($handler)) {
                    $this->registerCondition($name, $handler);
                }
            }
        }

        // Set up default aliases (only if not already configured)
        $this->setupDefaultAliases();
    }

    /**
     * Setup default condition aliases
     */
    private function setupDefaultAliases(): void
    {
        $defaultAliases = [
            'is_404' => ['404', 'not_found'],
            'is_archive' => ['archive'],
            'is_page' => ['page'],
            'is_single' => ['single', 'post'],
            'is_category' => ['category', 'cat'],
            'is_tag' => ['tag'],
            'is_home' => ['home', 'blog'],
            'is_front_page' => ['front_page', 'frontpage'],
            'is_search' => ['search'],
            'is_author' => ['author'],
            'is_date' => ['date'],
            'is_feed' => ['feed', 'rss'],
            'is_robots' => ['robots'],
            'is_favicon' => ['favicon'],
        ];

        foreach ($defaultAliases as $wpFunction => $aliases) {
            foreach ($aliases as $alias) {
                if (!isset($this->aliases[$alias])) {
                    $this->aliases[$alias] = $wpFunction;
                }
            }
        }
    }

    /**
     * Evaluate WordPress function with parameters
     */
    private function evaluateWordPressFunction(string $function, array $parameters): bool
    {
        try {
            if (empty($parameters)) {
                return (bool) $function();
            }

            return (bool) call_user_func_array($function, $parameters);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Evaluate custom condition
     */
    private function evaluateCustomCondition(string $condition, array $parameters): bool
    {
        try {
            $handler = $this->customConditions[$condition];

            if (empty($parameters)) {
                return (bool) $handler();
            }

            return (bool) call_user_func_array($handler, $parameters);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get parameter requirements for WordPress functions
     */
    private function getParameterRequirements(string $condition): ?array
    {
        $requirements = [
            'is_page' => ['string', 'int', 'array'],
            'is_single' => ['string', 'int', 'array'],
            'is_category' => ['string', 'int', 'array'],
            'is_tag' => ['string', 'int', 'array'],
            'is_tax' => ['string', 'array'],
            'is_author' => ['string', 'int', 'array'],
            'is_date' => [],
            'is_time' => [],
            'is_archive' => [],
            'is_search' => [],
            'is_404' => [],
            'is_home' => [],
            'is_front_page' => [],
            'is_feed' => ['string'],
            'is_page_template' => ['string'],
        ];

        return $requirements[$condition] ?? null;
    }

    /**
     * Check if parameter is of valid type
     */
    private function isValidParameterType(mixed $parameter, array $allowedTypes): bool
    {
        $parameterType = match (true) {
            is_string($parameter) => 'string',
            is_int($parameter) => 'int',
            is_array($parameter) => 'array',
            is_bool($parameter) => 'bool',
            default => 'unknown'
        };

        return in_array($parameterType, $allowedTypes, true);
    }
}
