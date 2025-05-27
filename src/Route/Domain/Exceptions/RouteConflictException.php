<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Exceptions;

use RuntimeException;

/**
 * Exception thrown when there's a conflict between routes
 */
final class RouteConflictException extends RuntimeException
{
    public static function duplicateRoute(string $routeId): self
    {
        return new self("Duplicate route detected: {$routeId}");
    }

    public static function conflictingConditions(string $condition1, string $condition2): self
    {
        return new self("Conflicting conditions: '{$condition1}' and '{$condition2}'");
    }

    public static function ambiguousMatch(array $routes): self
    {
        $routeIds = array_map(fn($route) => $route->getId(), $routes);
        $routeList = implode(', ', $routeIds);
        
        return new self("Ambiguous route match. Multiple routes could match: {$routeList}");
    }
}