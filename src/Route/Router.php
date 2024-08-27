<?php

declare(strict_types=1);

namespace Pollen\Route;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router as IlluminateRouter;
use Pollen\Route\Bindings\NullableWpPost;

class Router extends IlluminateRouter
{
    protected array $conditions = [];

    public function __construct(Dispatcher $events, ?Container $container = null)
    {
        parent::__construct($events, $container);
        $this->routes = new RouteCollection;
    }

    public function newRoute($methods, $uri, $action)
    {
        $this->setConditionsIfEmpty();

        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container)
            ->setConditions($this->conditions);
    }

    protected function findRoute($request)
    {
        if ($this->isWordPressAdminRequest()) {
            return $this->createAdminRoute($request);
        }

        return parent::findRoute($request);
    }

    public function setConditions(array $conditions = [])
    {
        $config = $this->container->make('config');
        $this->conditions = array_merge(
            $config->get('app.conditions', []),
            $conditions
        );
    }

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

    private function setConditionsIfEmpty(): void
    {
        if (empty($this->conditions)) {
            $this->setConditions();
        }
    }

    private function isWordPressAdminRequest(): bool
    {
        $app = $this->container['app'] ?? null;

        return $app && method_exists($app, 'isWordPressAdmin') && $app->isWordPressAdmin();
    }

    private function createAdminRoute($request): AdminRoute
    {
        $route = (new AdminRoute($request, $this))->get();
        $this->current = $route;
        $this->container->instance(Route::class, $route);

        return $route;
    }
}
