<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Services;

use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Exceptions\InvalidRouteConditionException;
use Pollora\Route\Domain\Models\RouteCondition;

/**
 * Service for validating route conditions
 * 
 * Ensures that route conditions are valid and can be properly
 * evaluated during route matching.
 */
final class ConditionValidator
{
    /**
     * WordPress conditional functions that require specific parameter types
     */
    private const PARAMETER_REQUIREMENTS = [
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
    ];

    public function __construct(
        private readonly ConditionResolverInterface $conditionResolver
    ) {}

    /**
     * Validate a route condition
     * 
     * @param RouteCondition $condition The condition to validate
     * @return bool True if condition is valid
     * @throws InvalidRouteConditionException If condition is invalid
     */
    public function validate(RouteCondition $condition): bool
    {
        $this->validateConditionType($condition);
        $this->validateConditionExists($condition);
        $this->validateParameters($condition);

        return true;
    }

    /**
     * Validate multiple conditions
     * 
     * @param RouteCondition[] $conditions Array of conditions
     * @return bool True if all conditions are valid
     * @throws InvalidRouteConditionException If any condition is invalid
     */
    public function validateMultiple(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $this->validate($condition);
        }

        return true;
    }

    /**
     * Check if a condition is safe to use
     * 
     * @param RouteCondition $condition The condition to check
     * @return bool True if condition is safe
     */
    public function isSafe(RouteCondition $condition): bool
    {
        try {
            return $this->validate($condition);
        } catch (InvalidRouteConditionException) {
            return false;
        }
    }

    /**
     * Get validation errors for a condition without throwing
     * 
     * @param RouteCondition $condition The condition to validate
     * @return array Array of validation error messages
     */
    public function getValidationErrors(RouteCondition $condition): array
    {
        $errors = [];

        try {
            $this->validateConditionType($condition);
        } catch (InvalidRouteConditionException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $this->validateConditionExists($condition);
        } catch (InvalidRouteConditionException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $this->validateParameters($condition);
        } catch (InvalidRouteConditionException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate WordPress condition exists and is callable
     * 
     * @param string $condition The condition name
     * @return bool True if condition exists
     */
    public function conditionExists(string $condition): bool
    {
        // Check if it's a WordPress function
        if (function_exists($condition)) {
            return true;
        }

        // Check if it's registered with the resolver
        return $this->conditionResolver->hasCondition($condition);
    }

    /**
     * Validate condition parameters for WordPress functions
     * 
     * @param string $condition The condition name
     * @param array $parameters The parameters to validate
     * @return bool True if parameters are valid
     */
    public function validateConditionParameters(string $condition, array $parameters): bool
    {
        if (!isset(self::PARAMETER_REQUIREMENTS[$condition])) {
            // Unknown condition, assume parameters are valid
            return true;
        }

        $allowedTypes = self::PARAMETER_REQUIREMENTS[$condition];

        // If no parameters are allowed, check that none are provided
        if (empty($allowedTypes)) {
            return empty($parameters);
        }

        // Validate each parameter type
        foreach ($parameters as $parameter) {
            $parameterType = $this->getParameterType($parameter);
            
            if (!in_array($parameterType, $allowedTypes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate condition type is supported
     * 
     * @param RouteCondition $condition The condition to validate
     * @throws InvalidRouteConditionException If type is invalid
     */
    private function validateConditionType(RouteCondition $condition): void
    {
        $validTypes = ['wordpress', 'laravel', 'custom'];
        
        if (!in_array($condition->getType(), $validTypes, true)) {
            throw InvalidRouteConditionException::unsupportedType($condition->getType());
        }
    }

    /**
     * Validate that the condition exists and is callable
     * 
     * @param RouteCondition $condition The condition to validate
     * @throws InvalidRouteConditionException If condition doesn't exist
     */
    private function validateConditionExists(RouteCondition $condition): void
    {
        if (empty($condition->getCondition())) {
            throw InvalidRouteConditionException::emptyCondition();
        }

        if ($condition->getType() === 'wordpress') {
            $conditionName = $condition->getCondition();
            
            if (!$this->conditionExists($conditionName)) {
                throw InvalidRouteConditionException::invalidWordPressFunction($conditionName);
            }
        }
    }

    /**
     * Validate condition parameters
     * 
     * @param RouteCondition $condition The condition to validate
     * @throws InvalidRouteConditionException If parameters are invalid
     */
    private function validateParameters(RouteCondition $condition): void
    {
        if ($condition->getType() === 'wordpress') {
            $conditionName = $condition->getCondition();
            $parameters = $condition->getParameters();
            
            if (!$this->validateConditionParameters($conditionName, $parameters)) {
                throw InvalidRouteConditionException::invalidParameters($conditionName, $parameters);
            }
        }
    }

    /**
     * Get the type of a parameter
     * 
     * @param mixed $parameter The parameter to check
     * @return string The parameter type
     */
    private function getParameterType(mixed $parameter): string
    {
        if (is_string($parameter)) {
            return 'string';
        }
        
        if (is_int($parameter)) {
            return 'int';
        }
        
        if (is_array($parameter)) {
            return 'array';
        }
        
        if (is_bool($parameter)) {
            return 'bool';
        }

        return 'unknown';
    }
}