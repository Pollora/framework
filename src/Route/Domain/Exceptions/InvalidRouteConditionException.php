<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when a route condition is invalid
 */
final class InvalidRouteConditionException extends InvalidArgumentException
{
    public static function unsupportedType(string $type): self
    {
        return new self("Unsupported route condition type: {$type}");
    }

    public static function emptyCondition(): self
    {
        return new self('Route condition cannot be empty');
    }

    public static function invalidWordPressFunction(string $function): self
    {
        return new self("Invalid WordPress function: {$function}");
    }

    public static function invalidParameters(string $condition, array $parameters): self
    {
        $paramString = json_encode($parameters);
        return new self("Invalid parameters for condition '{$condition}': {$paramString}");
    }
}