<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

/**
 * Port for resolving route conditions
 * 
 * Handles the resolution of WordPress conditional tags and custom conditions
 * for route matching.
 */
interface ConditionResolverInterface
{
    /**
     * Resolve a condition with optional parameters
     * 
     * @param string $condition The condition to resolve (e.g., 'is_page')
     * @param array $parameters Parameters to pass to the condition
     * @return bool True if condition is met
     */
    public function resolve(string $condition, array $parameters = []): bool;

    /**
     * Get all available conditions
     * 
     * @return array List of available condition names
     */
    public function getAvailableConditions(): array;

    /**
     * Register a custom condition handler
     * 
     * @param string $name The condition name
     * @param callable $handler The handler function
     * @return void
     */
    public function registerCondition(string $name, callable $handler): void;

    /**
     * Check if a condition is available
     * 
     * @param string $condition The condition name
     * @return bool True if condition is available
     */
    public function hasCondition(string $condition): bool;

    /**
     * Resolve an alias to the actual condition name
     * 
     * @param string $alias The alias to resolve
     * @return string The resolved condition name
     */
    public function resolveAlias(string $alias): string;

    /**
     * Register condition aliases
     * 
     * @param array $aliases Map of aliases to condition names
     * @return void
     */
    public function registerAliases(array $aliases): void;

    /**
     * Validate condition parameters
     * 
     * @param string $condition The condition name
     * @param array $parameters The parameters to validate
     * @return bool True if parameters are valid
     */
    public function validateParameters(string $condition, array $parameters): bool;
}