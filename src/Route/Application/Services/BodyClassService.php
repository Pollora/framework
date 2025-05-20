<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\BodyClassServiceInterface;
use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Service for managing HTML body classes in WordPress templates.
 */
class BodyClassService implements BodyClassServiceInterface
{
    /**
     * Modify the body classes based on the current route.
     *
     * @param array<string> $classes Current body classes
     * @param RouteEntity $route The current route
     * @return array<string> Modified body classes
     */
    public function modifyBodyClasses(array $classes, RouteEntity $route): array
    {
        // If this is a WordPress conditional route, return the original classes
        if ($route->hasCondition()) {
            return $classes;
        }

        // Extract tokens from the route to use as body classes
        $tokens = $this->getRouteTokens($route);

        if ($tokens !== []) {
            // Remove error404 class if we have route tokens (implies a valid route)
            return array_filter(
                array_merge($tokens, $classes), 
                fn ($class): bool => $class !== 'error404'
            );
        }

        return $classes;
    }
    
    /**
     * Extract tokens from a route for use in body classes.
     * 
     * @param RouteEntity $route The route to extract tokens from
     * @return array<string> Array of valid body class tokens
     */
    public function getRouteTokens(RouteEntity $route): array
    {
        // Extract tokens from route pattern
        $uri = $route->getUri();
        $segments = array_filter(explode('/', $uri));
        $parameters = $route->getParameters();
        
        $tokens = [];
        foreach ($segments as $segment) {
            // Handle parameter tokens
            if (preg_match('/^{([^}:]+)/', $segment, $matches)) {
                $paramName = $matches[1];
                if (isset($parameters[$paramName]) && is_string($parameters[$paramName])) {
                    $tokens[] = $paramName . '-' . $this->sanitizeClassname($parameters[$paramName]);
                }
                continue;
            }
            
            // Handle static text
            if (!empty($segment)) {
                $tokens[] = $this->sanitizeClassname($segment);
            }
        }
        
        return array_filter($tokens);
    }
    
    /**
     * Sanitize a string for use as a CSS class name.
     *
     * @param string $value The value to sanitize
     * @return string Sanitized class name
     */
    private function sanitizeClassname(string $value): string 
    {
        // Replace spaces and special characters with dashes
        $sanitized = preg_replace('/[^a-z0-9_-]/i', '-', $value);
        // Convert to lowercase
        return strtolower($sanitized);
    }
} 