<?php

declare(strict_types=1);

namespace Pollen\Route;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router as IlluminateRouter;
use Pollen\Route\Bindings\NullableWpPost;

class Router extends IlluminateRouter
{
    /**
     * WordPress conditions.
     *
     * @var array
     */
    protected $conditions = [];

    public function __construct(Dispatcher $events, ?Container $container = null)
    {
        parent::__construct($events, $container);
        $this->routes = new RouteCollection();
    }

    /**
     * Create a new Route object.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed  $action
     * @return \Illuminate\Routing\Route
     */
    public function newRoute($methods, $uri, $action)
    {
        // WordPress condition could have been already applied.
        // We only try one more time to fetch them if no conditions
        // are registered. This avoids to overwrite any pre-existing rules.
        if (empty($this->conditions)) {
            $this->setConditions();
        }

        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container)
            ->setConditions($this->conditions);
    }

    /**
     * Find the route matching a given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Routing\Route
     *
     * @throws \Illuminate\Container\EntryNotFoundException
     */
    protected function findRoute($request)
    {
        $app = $this->container['app'] ?? null;

        // Verify that we're currently requesting a WordPress administration page.
        // If so, let's return a catch-all route.
        if (! is_null($app) && method_exists($app, 'isWordPressAdmin') && $app->isWordPressAdmin()) {
            $this->current = $route = (new AdminRoute($request, $this))->get();
            $this->container->instance(Route::class, $route);

            return $route;
        }

        return parent::findRoute($request);
    }

    /**
     * Setup WordPress conditions.
     */
    public function setConditions(array $conditions = [])
    {
        $config = $this->container->has('config') ? $this->container->make('config') : null;

        if (! is_null($config)) {
            $this->conditions = array_merge(
                $config->get('app.conditions', []),
                $conditions,
            );
        } else {
            $this->conditions = $conditions;
        }
    }

    /**
     * Add WordPress default parameters if WordPress route.
     *
     * @param  \Pollen\Route\Route  $route
     * @return \Pollen\Route\Route
     */
    public function addWordPressBindings($route)
    {
        global $post, $wp_query;

        foreach (compact('post', 'wp_query') as $key => $value) {
            if ($key === 'post' && $value === null && class_exists('WP_Post')) {
                $value = (new NullableWpPost())
                    ->toWpPost();
            }

            $route->setParameter($key, $value);
        }

        return $route;
    }
}
