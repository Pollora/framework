<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Exceptions\InvalidRouteConditionException;

/**
 * Value object representing a route condition
 *
 * This class encapsulates WordPress conditional logic and provides
 * evaluation capabilities for route matching.
 */
final class RouteCondition
{
    private const VALID_TYPES = ['wordpress', 'laravel', 'custom'];

    private function __construct(
        private readonly string $type,
        private readonly string $condition,
        private readonly array $parameters = []
    ) {}

    /**
     * Create a RouteCondition from a WordPress conditional tag
     * 
     * Validates and resolves aliases through the condition resolver if available.
     * 
     * @param string $tag WordPress condition or alias
     * @param array $parameters Condition parameters
     * @param ConditionResolverInterface|null $resolver Optional resolver for alias validation
     * @throws InvalidRouteConditionException If the condition is invalid
     */
    public static function fromWordPressTag(
        string $tag, 
        array $parameters = [], 
        ?ConditionResolverInterface $resolver = null
    ): self {
        // If resolver is provided, validate and resolve the condition
        if ($resolver !== null) {
            $resolvedCondition = $resolver->resolveAlias($tag);
            
            // Validate that the resolved condition exists
            if (!$resolver->hasCondition($resolvedCondition)) {
                throw new InvalidRouteConditionException(
                    "WordPress condition '{$tag}' (resolved to '{$resolvedCondition}') is not available."
                );
            }
            
            // Validate parameters
            if (!$resolver->validateParameters($resolvedCondition, $parameters)) {
                throw new InvalidRouteConditionException(
                    "Invalid parameters for WordPress condition '{$resolvedCondition}'."
                );
            }
            
            return new self('wordpress', $resolvedCondition, $parameters);
        }
        
        // Fallback: create without validation for backward compatibility
        return new self('wordpress', $tag, $parameters);
    }

    /**
     * Create a RouteCondition for Laravel routing
     */
    public static function fromLaravel(string $pattern): self
    {
        return new self('laravel', $pattern);
    }

    /**
     * Create a custom RouteCondition
     */
    public static function fromCustom(string $condition, array $parameters = []): self
    {
        return new self('custom', $condition, $parameters);
    }

    /**
     * Create a validated WordPress condition with resolver
     * 
     * @param string $tag WordPress condition or alias
     * @param array $parameters Condition parameters  
     * @param ConditionResolverInterface $resolver Condition resolver for validation
     * @throws InvalidRouteConditionException If the condition is invalid
     */
    public static function createValidated(
        string $tag,
        array $parameters,
        ConditionResolverInterface $resolver
    ): self {
        return self::fromWordPressTag($tag, $parameters, $resolver);
    }

    /**
     * Evaluate the condition against the given context
     */
    public function evaluate(array $context = []): bool
    {
        return match ($this->type) {
            'wordpress' => $this->evaluateWordPressCondition($context),
            'laravel' => $this->evaluateLaravelCondition($context),
            'custom' => $this->evaluateCustomCondition($context),
            default => false
        };
    }

    /**
     * Get the specificity score for priority resolution
     *
     * Higher values indicate more specific conditions
     */
    public function getSpecificity(): int
    {
        $baseScore = match ($this->type) {
            'custom' => 1000,
            'wordpress' => 500,
            'laravel' => 100,
            default => 0
        };

        // Add parameter specificity
        $parameterScore = count($this->parameters) * 50;

        // Add condition-specific scoring for WordPress
        if ($this->type === 'wordpress') {
            $conditionScore = match (true) {
                str_contains($this->condition, 'is_page') => 100,
                str_contains($this->condition, 'is_single') => 90,
                str_contains($this->condition, 'is_category') => 80,
                str_contains($this->condition, 'is_tag') => 70,
                str_contains($this->condition, 'is_archive') => 60,
                str_contains($this->condition, 'is_home') => 50,
                str_contains($this->condition, 'is_front_page') => 40,
                default => 30
            };
            $baseScore += $conditionScore;
        }

        return $baseScore + $parameterScore;
    }

    /**
     * Generate a unique identifier for this condition
     */
    public function toUniqueIdentifier(): string
    {
        $identifier = $this->condition;

        if (! empty($this->parameters)) {
            $identifier .= '_'.md5(serialize($this->parameters));
        }

        return $identifier;
    }

    /**
     * Check if this condition is more specific than another
     */
    public function isMoreSpecificThan(self $other): bool
    {
        return $this->getSpecificity() > $other->getSpecificity();
    }

    /**
     * Get the condition type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the condition string
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Get the condition parameters
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Check if condition has parameters
     */
    public function hasParameters(): bool
    {
        return ! empty($this->parameters);
    }

    /**
     * Evaluate WordPress conditional tag
     */
    private function evaluateWordPressCondition(array $context): bool
    {
        // Check if function exists
        if (! function_exists($this->condition)) {
            return false;
        }

        try {
            // Call WordPress function with parameters
            return (bool) call_user_func_array($this->condition, $this->parameters);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Evaluate Laravel pattern condition
     */
    private function evaluateLaravelCondition(array $context): bool
    {
        // For Laravel conditions, we rely on Laravel's native matching
        // This method is primarily for compatibility
        return true;
    }

    /**
     * Evaluate custom condition
     */
    private function evaluateCustomCondition(array $context): bool
    {
        // Custom conditions need to be registered with a resolver
        // For now, return false as they need external handling
        return false;
    }
}
