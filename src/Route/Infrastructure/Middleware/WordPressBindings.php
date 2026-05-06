<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\WordPressRoutingService;

/**
 * Middleware to handle WordPress-specific route bindings.
 *
 * Adds WordPress-specific objects (like current post and query) to routes
 * that have WordPress conditions, enabling type-hinted injection in controllers.
 */
class WordPressBindings
{
    public function __construct(
        private readonly WordPressRoutingService $routingService
    ) {}

    /**
     * Handle the incoming request.
     *
     * Adds WordPress bindings to routes that have WordPress conditions.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();

        if ($route instanceof Route && $route->hasCondition()) {
            $this->routingService->bindWordPressParameters($route);
        }

        return $next($request);
    }
}
