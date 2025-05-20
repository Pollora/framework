<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Interface for body class management services.
 *
 * Defines methods for modifying HTML body classes based on the current route.
 */
interface BodyClassServiceInterface
{
    /**
     * Modify the body classes based on the current route.
     *
     * @param array<string> $classes Current body classes
     * @param RouteEntity $route The current route
     * @return array<string> Modified body classes
     */
    public function modifyBodyClasses(array $classes, RouteEntity $route): array;
    
    /**
     * Extract tokens from a route for use in body classes.
     * 
     * @param RouteEntity $route The route to extract tokens from
     * @return array<string> Array of valid body class tokens
     */
    public function getRouteTokens(RouteEntity $route): array;
} 