<?php

declare(strict_types=1);

namespace Pollora\Route\Middleware;

use Closure;
use Pollora\Route\Router;

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
     *
     * @param  Router  $router  The router instance
     */
    public function __construct(private readonly Router $router) {}

    /**
     * Handle the incoming request.
     *
     * Adds WordPress bindings to routes that have WordPress conditions.
     *
     * @param  mixed  $request  The incoming request
     * @param  Closure  $next  The next middleware handler
     * @return mixed The response
     */
    public function handle($request, Closure $next)
    {
        $route = $request->route();

        if ($route->hasCondition()) {
            $this->router->addWordPressBindings($route);
        }

        return $next($request);
    }
}
