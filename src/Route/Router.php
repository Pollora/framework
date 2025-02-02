<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router as IlluminateRouter;
use Pollora\Route\Bindings\NullableWpPost;

/**
 * Extended Router that provides WordPress-specific routing functionality.
 *
 * This class extends Laravel's Router to handle WordPress-specific routing needs,
 * including admin routes, WordPress conditions, and post bindings.
 */
class Router extends IlluminateRouter
{
    /**
     * Array of WordPress routing conditions.
     *
     * @var array<string, mixed> Registered WordPress routing conditions
     */
    protected array $conditions = [];

    /**
     * Create a new Router instance.
     *
     * @param  Dispatcher  $events  Event dispatcher instance
     * @param  Container|null  $container  Service container instance
     */
    public function __construct(Dispatcher $events, ?Container $container = null)
    {
        parent::__construct($events, $container);
        $this->routes = new RouteCollection;
    }

    /**
     * Create a new Route instance.
     *
     * @param  array<string>  $methods  HTTP methods
     * @param  string  $uri  URI pattern
     * @param  mixed  $action  Route action
     * @return \Pollora\Route\Route New route instance with WordPress conditions
     */
    public function newRoute($methods, $uri, $action): \Pollora\Route\Route
    {
        $this->setConditionsIfEmpty();

        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container)
            ->setConditions($this->conditions);
    }

    /**
     * Find the route matching a given request.
     *
     * Handles special case for WordPress admin requests by creating
     * a dedicated admin route.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Route|AdminRoute
     */
    protected function findRoute($request)
    {
        if ($this->isWordPressAdminRequest()) {
            return $this->createAdminRoute($request);
        }

        return parent::findRoute($request);
    }

    /**
     * Set WordPress routing conditions.
     *
     * Merges provided conditions with those from configuration.
     *
     * @param  array<string, mixed>  $conditions  Additional conditions to set
     */
    public function setConditions(array $conditions = []): void
    {
        $config = $this->container->make('config');
        $this->conditions = array_merge(
            $config->get('app.conditions', []),
            $conditions
        );
    }

    /**
     * Add WordPress-specific bindings to a route.
     *
     * Binds current WordPress post and query objects to the route parameters.
     *
     * @param  Route  $route  Route to add bindings to
     * @return Route Route with WordPress bindings
     */
    public function addWordPressBindings($route)
    {
        global $post, $wp_query;

        $bindings = [
            'post' => $post ?? (new NullableWpPost)->toWpPost(),
            'wp_query' => $wp_query,
        ];

        foreach ($bindings as $key => $value) {
            $route->setParameter($key, $value);
        }

        return $route;
    }

    /**
     * Ensure conditions are set if they haven't been initialized.
     */
    private function setConditionsIfEmpty(): void
    {
        if ($this->conditions === []) {
            $this->setConditions();
        }
    }

    /**
     * Determine if the current request is for the WordPress admin area.
     *
     * @return bool True if request is for wp-admin, false otherwise
     */
    private function isWordPressAdminRequest(): bool
    {
        $app = $this->container['app'] ?? null;

        return $app && method_exists($app, 'isWordPressAdmin') && $app->isWordPressAdmin();
    }

    /**
     * Create a new WordPress admin route.
     *
     * Sets up a route specifically for handling WordPress admin requests
     * and registers it in the container.
     *
     * @param  \Illuminate\Http\Request  $request  Current request instance
     * @return AdminRoute Configured admin route
     */
    private function createAdminRoute($request): AdminRoute
    {
        $route = (new AdminRoute($request, $this))->get();
        $this->current = $route;
        $this->container->instance(Route::class, $route);

        return $route;
    }
}
