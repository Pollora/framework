<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Closure;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Route\Domain\Contracts\BodyClassServiceInterface;

/**
 * Laravel middleware to manage WordPress body classes.
 *
 * Handles the addition and modification of CSS classes applied to the body tag
 * based on the current route configuration.
 */
class LaravelBodyClassMiddleware
{
    /**
     * Create a new body class middleware instance.
     */
    public function __construct(
        private readonly BodyClassServiceInterface $bodyClassService,
        private readonly LaravelRoute $routeAdapter,
        private readonly Filter $filter
    ) {}

    /**
     * Handle the incoming request.
     *
     * Adds a filter to modify the WordPress body classes based on the current route.
     *
     * @param  mixed  $request  The incoming request
     * @param  Closure  $next  The next middleware handler
     * @return mixed The response
     */
    public function handle($request, Closure $next)
    {
        $laravelRoute = $request->route();
        
        // If there's a valid route, add the body class filter
        if ($laravelRoute !== null) {
            // Convert to domain entity
            $routeEntity = $this->routeAdapter->toDomainEntity($laravelRoute);
            
            // Add filter to WordPress body_class hook
            $this->filter->add('body_class', function (array $classes) use ($routeEntity): array {
                return $this->bodyClassService->modifyBodyClasses($classes, $routeEntity);
            });
        }

        return $next($request);
    }
} 