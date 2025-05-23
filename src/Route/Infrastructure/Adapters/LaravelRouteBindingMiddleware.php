<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Pollora\Route\Domain\Contracts\BindingServiceInterface;

/**
 * Laravel middleware to handle WordPress-specific route bindings.
 *
 * Adds WordPress-specific objects (like current post and query) to routes
 * that have WordPress conditions.
 */
class LaravelRouteBindingMiddleware
{
    /**
     * Create a new WordPress bindings middleware instance.
     */
    public function __construct(
        private readonly BindingServiceInterface $bindingService,
        private readonly LaravelRoute $routeAdapter,
        private readonly Router $router
    ) {}

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
        $laravelRoute = $request->route();

        // If this is a WordPress route with conditions
        if ($laravelRoute instanceof Route && $laravelRoute->hasCondition()) {
            // Convert the Laravel route to a domain entity
            $routeEntity = $this->routeAdapter->toDomainEntity($laravelRoute);

            // Add WordPress bindings
            if ($this->bindingService->shouldAddBindings($routeEntity)) {
                $boundRouteEntity = $this->bindingService->addBindings($routeEntity);

                // Apply bindings back to Laravel route
                foreach ($boundRouteEntity->getParameters() as $key => $value) {
                    $laravelRoute->setParameter($key, $value);
                }
            }
        }

        return $next($request);
    }
}
