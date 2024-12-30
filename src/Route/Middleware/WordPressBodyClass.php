<?php

declare(strict_types=1);

namespace Pollora\Route\Middleware;

use Closure;
use Pollora\Hook\Filter;
use Pollora\Route\Route;

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
     *
     * @param Filter $filter WordPress filter handler
     */
    public function __construct(protected Filter $filter) {}

    /**
     * Handle the incoming request.
     *
     * Adds a filter to modify the WordPress body classes based on the current route.
     *
     * @param mixed $request The incoming request
     * @param Closure $next The next middleware handler
     * @return mixed The response
     */
    public function handle($request, Closure $next)
    {
        $this->filter->add('body_class', $this->getBodyClassCallback($request->route()));

        return $next($request);
    }

    /**
     * Get the callback for modifying body classes.
     *
     * @param Route $route Current route instance
     * @return Closure Callback that modifies body classes
     */
    private function getBodyClassCallback(Route $route): Closure
    {
        return function (array $classes) use ($route): array {
            if ($route->hasCondition()) {
                return $classes;
            }

            $tokens = $this->getRouteTokens($route);

            if ($tokens !== []) {
                return array_filter(array_merge($tokens, $classes), fn ($class): bool => $class !== 'error404');
            }

            return $classes;
        };
    }

    /**
     * Extract route tokens for body classes.
     *
     * @param Route $route Current route instance
     * @return array<string> Array of valid route tokens
     */
    private function getRouteTokens(Route $route): array
    {
        return array_filter(array_map(fn ($token) => match ($token[0]) {
            'variable' => $this->handleVariableToken($token, $route),
            'text' => sanitize_title($token[1]),
            default => false,
        }, array_reverse($route->getCompiled()->getTokens())));
    }

    /**
     * Handle variable tokens in the route.
     *
     * @param array $token Token information
     * @param Route $route Current route instance
     * @return string|false Generated class name or false if invalid
     */
    private function handleVariableToken(array $token, Route $route): string|false
    {
        if (isset($token[3]) && $route->hasParameter($paramKey = $token[3])) {
            $param = $route->parameter($paramKey);

            return is_string($param) ? sprintf('%s-%s', $paramKey, sanitize_title($param)) : false;
        }

        return false;
    }
}
