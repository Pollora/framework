<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

/**
 * Domain contract for resolving WordPress routing conditions.
 *
 * This interface defines the domain-level abstraction for condition resolution,
 * ensuring the domain layer remains independent of infrastructure concerns.
 * It allows routes to resolve condition aliases to their actual WordPress
 * function names without depending on concrete implementations.
 *
 * The resolver should support both built-in WordPress conditions and custom
 * conditions defined in application configuration, providing a unified way
 * to access condition mappings across the application.
 */
interface ConditionResolverInterface
{
    /**
     * Resolve a condition alias to its actual WordPress function name.
     *
     * Takes a condition alias (user-friendly name or WordPress function)
     * and returns the corresponding WordPress conditional function name.
     * If the condition alias is not found in registered conditions, returns
     * the original condition string to allow direct WordPress function usage.
     *
     * @param  string  $condition  The condition alias to resolve
     * @return string The resolved WordPress function name or original condition
     *
     * @example
     * $resolver->resolveCondition('single'); // Returns 'is_single'
     * $resolver->resolveCondition('page');   // Returns 'is_page'
     * $resolver->resolveCondition('is_home'); // Returns 'is_home' (passthrough)
     */
    public function resolveCondition(string $condition): string;

    /**
     * Get all available condition aliases and their WordPress function mappings.
     *
     * Returns an associative array where keys are condition aliases and values
     * are the corresponding WordPress function names. This provides access to
     * all registered conditions for validation, debugging, or documentation.
     *
     * @return array<string, string> Array of condition aliases mapped to WordPress functions
     *
     * @example
     * $resolver->getConditions(); // Returns ['single' => 'is_single', 'page' => 'is_page', ...]
     */
    public function getConditions(): array;
}
