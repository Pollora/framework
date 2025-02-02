<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Route\Router;

/**
 * Facade for WordPress Router.
 *
 * Provides a Laravel-style routing interface for WordPress, including
 * support for route conditions, authentication, and WordPress-specific bindings.
 *
 * @method static array setConditions(array $conditions = []) Set route conditions
 * @method static \Themosis\Route\Route addWordPressBindings(\Themosis\Route\Route $route) Add WordPress route bindings
 * @method static void auth(array $options = []) Add authentication middleware
 * @method static \Illuminate\Routing\Route get(string $uri, \Closure|array|string|callable|null $action = null) Add GET route
 * @method static \Illuminate\Routing\Route post(string $uri, \Closure|array|string|callable|null $action = null) Add POST route
 * @method static \Illuminate\Routing\Route put(string $uri, \Closure|array|string|callable|null $action = null) Add PUT route
 * @method static \Illuminate\Routing\Route delete(string $uri, \Closure|array|string|callable|null $action = null) Add DELETE route
 * @method static \Illuminate\Routing\Route patch(string $uri, \Closure|array|string|callable|null $action = null) Add PATCH route
 * @method static \Illuminate\Routing\Route options(string $uri, \Closure|array|string|callable|null $action = null) Add OPTIONS route
 * @method static \Illuminate\Routing\Route any(string $uri, \Closure|array|string|callable|null $action = null) Add route for any method
 * @method static \Illuminate\Routing\Route match(array|string $methods, string $uri, \Closure|array|string|callable|null $action = null) Add route for specific methods
 * @method static \Illuminate\Routing\RouteRegistrar prefix(string $prefix) Add route prefix
 * @method static \Illuminate\Routing\RouteRegistrar where(array $where) Add route constraints
 * @method static \Illuminate\Routing\PendingResourceRegistration resource(string $name, string $controller, array $options = []) Add resource route
 * @method static \Illuminate\Routing\PendingResourceRegistration apiResource(string $name, string $controller, array $options = []) Add API resource route
 * @method static void apiResources(array $resources) Add multiple API resources
 * @method static \Illuminate\Routing\RouteRegistrar middleware(array|string|null $middleware) Add route middleware
 * @method static \Illuminate\Routing\Route substituteBindings(\Illuminate\Support\Facades\Route $route) Substitute route bindings
 * @method static void substituteImplicitBindings(\Illuminate\Support\Facades\Route $route) Substitute implicit bindings
 * @method static \Illuminate\Routing\RouteRegistrar as(string $value) Add route name
 * @method static \Illuminate\Routing\RouteRegistrar domain(string $value) Add route domain
 * @method static \Illuminate\Routing\RouteRegistrar name(string $value) Add route name
 * @method static \Illuminate\Routing\RouteRegistrar namespace(string $value) Add route namespace
 * @method static \Illuminate\Routing\Router|\Illuminate\Routing\RouteRegistrar group(array|\Closure|string $attributes, \Closure|string $routes) Create route group
 * @method static \Illuminate\Routing\Route redirect(string $uri, string $destination, int $status = 302) Add redirect route
 * @method static \Illuminate\Routing\Route permanentRedirect(string $uri, string $destination) Add permanent redirect route
 * @method static \Illuminate\Routing\Route view(string $uri, string $view, array $data = []) Add view route
 * @method static void bind(string $key, string|callable $binder) Add route parameter binding
 * @method static void model(string $key, string $class, \Closure|null $callback = null) Add model binding
 * @method static \Illuminate\Routing\Route current() Get current route
 * @method static string|null currentRouteName() Get current route name
 * @method static string|null currentRouteAction() Get current route action
 *
 * @see \Pollora\Route\Router
 */
class Route extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}
