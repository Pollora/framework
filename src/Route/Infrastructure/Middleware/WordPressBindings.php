<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

/**
 * Middleware to handle WordPress-specific route bindings.
 *
 * Adds WordPress-specific objects (like current post and query) to routes
 * that have WordPress conditions.
 */
class WordPressBindings
{
    /**
     * Create a new WordPress bindings middleware instance.
     */
    public function __construct(private readonly ExtendedRouter $router) {}

    /**
     * Handle the incoming request.
     *
     * Adds WordPress bindings to routes that have WordPress conditions.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();

        if ($route && method_exists($route, 'hasCondition') && $route->hasCondition()) {
            $this->router->addWordPressBindings($route);
        }

        return $next($request);
    }
}
