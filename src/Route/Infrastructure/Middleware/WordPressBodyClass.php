<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pollora\Route\Domain\Models\Route;

/**
 * Middleware to manage WordPress body classes.
 *
 * Handles the addition and modification of CSS classes applied to the body tag
 * based on the current route configuration.
 */
class WordPressBodyClass
{
    /**
     * Create a new body class middleware instance.
     */
    public function __construct() {}

    /**
     * Handle the incoming request.
     *
     * Adds a filter to modify the WordPress body classes based on the current route.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();
        
        if ($route instanceof Route && function_exists('add_filter')) {
            add_filter('body_class', $this->getBodyClassCallback($route));
        }

        return $next($request);
    }

    /**
     * Get the callback for modifying body classes.
     *
     * @param  Route  $route
     * @return Closure
     */
    private function getBodyClassCallback(Route $route): Closure
    {
        return function (array $classes) use ($route): array {
            // Don't modify classes for WordPress routes (they handle their own)
            if ($route->hasCondition()) {
                return $classes;
            }

            $tokens = $this->getRouteTokens($route);

            if ($tokens !== []) {
                return array_filter(
                    array_merge($tokens, $classes), 
                    fn ($class): bool => $class !== 'error404'
                );
            }

            return $classes;
        };
    }

    /**
     * Extract route tokens for body classes.
     *
     * @param  Route  $route
     * @return array<string>
     */
    private function getRouteTokens(Route $route): array
    {
        if (!method_exists($route, 'getCompiled')) {
            return [];
        }
        
        $compiled = $route->getCompiled();
        if (!$compiled || !method_exists($compiled, 'getTokens')) {
            return [];
        }
        
        return array_filter(array_map(
            fn ($token) => match ($token[0]) {
                'variable' => $this->handleVariableToken($token, $route),
                'text' => $this->sanitizeClass($token[1]),
                default => false,
            }, 
            array_reverse($compiled->getTokens())
        ));
    }

    /**
     * Handle variable tokens in the route.
     *
     * @param  array  $token
     * @param  Route  $route
     * @return string|false
     */
    private function handleVariableToken(array $token, Route $route): string|false
    {
        if (isset($token[3]) && $route->hasParameter($paramKey = $token[3])) {
            $param = $route->parameter($paramKey);

            return is_string($param) ? 
                sprintf('%s-%s', $paramKey, $this->sanitizeClass($param)) : 
                false;
        }

        return false;
    }

    /**
     * Sanitize a string for use as a CSS class.
     *
     * @param  string  $text
     * @return string
     */
    private function sanitizeClass(string $text): string
    {
        if (function_exists('sanitize_title')) {
            return sanitize_title($text);
        }
        
        // Fallback sanitization
        return strtolower(preg_replace('/[^a-zA-Z0-9\-_]/', '-', trim($text)));
    }
}