<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Pollora\Http\Controllers\FrontendController;
use Pollora\Route\Domain\Contracts\RouterInterface;
use Pollora\Route\Domain\Models\RouteEntity;

/**
 * Laravel implementation of the router interface.
 *
 * This class adapts the domain router interface to Laravel's router implementation.
 */
class LaravelRouter implements RouterInterface
{
    /**
     * @var Router The underlying Laravel router
     */
    private Router $laravelRouter;

    /**
     * @var LaravelRoute The route adapter
     */
    private LaravelRoute $routeAdapter;

    /**
     * @var Container The service container
     */
    private Container $container;

    /**
     * Create a new router adapter.
     */
    public function __construct(
        Dispatcher $events,
        Container $container,
        LaravelRoute $routeAdapter
    ) {
        $this->laravelRouter = new Router($events, $container);
        $this->routeAdapter = $routeAdapter;
        $this->container = $container;
    }

    /**
     * Create a new route entity.
     *
     * @param  array<int, string>  $methods  HTTP methods
     * @param  string  $uri  URI pattern
     * @param  mixed  $action  Route action
     * @return RouteEntity The created route entity
     */
    public function newRoute(array $methods, string $uri, $action): RouteEntity
    {
        // Create a new Laravel route
        $laravelRoute = $this->laravelRouter->newRoute($methods, $uri, $action);

        // Convert to domain entity
        return $this->routeAdapter->toDomainEntity($laravelRoute);
    }

    /**
     * Find the route matching a given request.
     *
     * @param  mixed  $request  The request to match
     * @return RouteEntity|null The matching route or null if not found
     */
    public function findRoute($request): ?RouteEntity
    {
        // Ensure we're working with a Laravel Request
        if (! $request instanceof Request) {
            throw new \InvalidArgumentException('Request must be an instance of Illuminate\Http\Request');
        }

        try {
            // Find the Laravel route
            $laravelRoute = $this->laravelRouter->findRoute($request);

            // Convert to domain entity
            return $this->routeAdapter->toDomainEntity($laravelRoute);
        } catch (\Exception $e) {
            // Handle the not found exception
            return null;
        }
    }

    /**
     * Set WordPress conditions for routes.
     *
     * @param  array<string, mixed>  $conditions  Mapping of condition signatures to routes
     */
    public function setConditions(array $conditions = []): void
    {
        $this->laravelRouter->setConditions($conditions);
    }

    /**
     * Add a route to the router's collection.
     *
     * @param  RouteEntity  $route  The route to add
     */
    public function addRoute(RouteEntity $route): void
    {
        // Create a new Laravel route
        $laravelRoute = new Route(
            $route->getMethods(),
            $route->getUri(),
            $route->getAction()
        );

        // Convert and configure the Laravel route
        $frameworkRoute = $this->routeAdapter->toFrameworkRoute($route, $laravelRoute);

        // Add to the router's collection
        $this->laravelRouter->getRoutes()->add($frameworkRoute);
    }

    /**
     * Get the underlying Laravel router.
     */
    public function getLaravelRouter(): Router
    {
        return $this->laravelRouter;
    }

    /**
     * Create an admin route for WordPress admin requests.
     */
    public function createAdminRoute(Request $request): RouteEntity
    {
        $config = $this->container->make('config');
        $adminRoute = new AdminRoute($request, $this->laravelRouter, $config);
        $laravelRoute = $adminRoute->get();

        return $this->routeAdapter->toDomainEntity($laravelRoute);
    }

    /**
     * Create a fallback route for WordPress frontend requests.
     */
    public function createFallbackRoute(Request $request): RouteEntity
    {
        $laravelRoute = $this->laravelRouter->any(
            '{any}',
            [FrontendController::class, 'handle']
        )->where('any', '.*');

        // Apply WordPress middleware (would be done in actual implementation)

        // Bind the request to the route
        $laravelRoute->bind($request);

        return $this->routeAdapter->toDomainEntity($laravelRoute);
    }
}
