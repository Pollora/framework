<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Route\Domain\Contracts\RouteRegistryInterface;
use Pollora\Route\Domain\Services\RouteBuilder;

/**
 * Route facade for simplified WordPress/Laravel routing.
 * 
 * Provides a clean API for registering routes using the simplified
 * order-based routing system without priority calculations.
 * 
 * @author Pollora Framework
 * 
 * @method static RouteBuilder create()
 * @method static RouteBuilder id(string $id)
 * @method static RouteBuilder methods(string ...$methods)
 * @method static RouteBuilder uri(string $pattern)
 * @method static RouteBuilder where(callable $callback)
 * @method static RouteBuilder action(mixed $action)
 * @method static RouteBuilder middleware(string ...$middleware)
 */
final class Route extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return RouteBuilder::class;
    }

    /**
     * Create a WordPress route with GET method.
     * 
     * Convenient method for creating WordPress conditional routes.
     * Routes are automatically registered in the route registry.
     * 
     * Example:
     * ```php
     * Route::wp('single', function() {
     *     return view('single-post');
     * });
     * ```
     *
     * @param string $condition WordPress conditional tag or alias
     * @param mixed $action Route action
     * @return void
     */
    public static function wp(string $condition, mixed $action = null): void
    {
        $builder = static::getFacadeRoot()->create()->get()->wp($condition);
        
        if ($action !== null) {
            $builder = $builder->action($action);
            $route = $builder->build();
            app(RouteRegistryInterface::class)->register($route);
        }
    }

    /**
     * Create a WordPress route with custom parameters.
     * 
     * Example:
     * ```php
     * Route::wordpress('single', ['post_type' => 'product'], function() {
     *     return view('single-product');
     * });
     * ```
     *
     * @param string $condition WordPress conditional tag or alias
     * @param array<string, mixed> $parameters Parameters for the conditional tag
     * @param mixed $action Route action
     * @return void
     */
    public static function wordpress(string $condition, array $parameters = [], mixed $action = null): void
    {
        $builder = static::getFacadeRoot()->create()->get()->wordpress($condition, $parameters);
        
        if ($action !== null) {
            $builder = $builder->action($action);
            $route = $builder->build();
            app(RouteRegistryInterface::class)->register($route);
        }
    }

    /**
     * Create a GET URI pattern route.
     * 
     * Example:
     * ```php
     * Route::get('/api/posts/{id}', function($id) {
     *     return Post::find($id);
     * });
     * ```
     *
     * @param string $pattern URI pattern
     * @param mixed $action Route action
     * @return void
     */
    public static function get(string $pattern, mixed $action = null): void
    {
        $builder = static::getFacadeRoot()->create()->get()->uri($pattern);
        
        if ($action !== null) {
            $builder = $builder->action($action);
            $route = $builder->build();
            app(RouteRegistryInterface::class)->register($route);
        }
    }

    /**
     * Create a POST URI pattern route.
     *
     * @param string $pattern URI pattern
     * @param mixed $action Route action
     * @return void
     */
    public static function post(string $pattern, mixed $action = null): void
    {
        $builder = static::getFacadeRoot()->create()->post()->uri($pattern);
        
        if ($action !== null) {
            $builder = $builder->action($action);
            $route = $builder->build();
            app(RouteRegistryInterface::class)->register($route);
        }
    }

    /**
     * Create a PUT URI pattern route.
     *
     * @param string $pattern URI pattern
     * @param mixed $action Route action
     * @return void
     */
    public static function put(string $pattern, mixed $action = null): void
    {
        $builder = static::getFacadeRoot()->create()->put()->uri($pattern);
        
        if ($action !== null) {
            $builder = $builder->action($action);
            $route = $builder->build();
            app(RouteRegistryInterface::class)->register($route);
        }
    }

    /**
     * Create a DELETE URI pattern route.
     *
     * @param string $pattern URI pattern
     * @param mixed $action Route action
     * @return void
     */
    public static function delete(string $pattern, mixed $action = null): void
    {
        $builder = static::getFacadeRoot()->create()->delete()->uri($pattern);
        
        if ($action !== null) {
            $builder = $builder->action($action);
            $route = $builder->build();
            app(RouteRegistryInterface::class)->register($route);
        }
    }

    /**
     * Create a PATCH URI pattern route.
     *
     * @param string $pattern URI pattern
     * @param mixed $action Route action
     * @return void
     */
    public static function patch(string $pattern, mixed $action = null): void
    {
        $builder = static::getFacadeRoot()->create()->patch()->uri($pattern);
        
        if ($action !== null) {
            $builder = $builder->action($action);
            $route = $builder->build();
            app(RouteRegistryInterface::class)->register($route);
        }
    }

    /**
     * Create a route builder instance for complex routing.
     * 
     * Use this method when you need to configure routes with multiple
     * options like middleware, custom conditions, etc.
     * 
     * Example:
     * ```php
     * Route::builder()
     *     ->wp('single', ['post_type' => 'product'])
     *     ->middleware('auth', 'throttle')
     *     ->action(ProductController::class . '@show')
     *     ->register();
     * ```
     *
     * @return \Pollora\Route\Domain\Services\RouteBuilder
     */
    public static function builder(): RouteBuilder
    {
        return static::getFacadeRoot()->create();
    }

    /**
     * Register a built route manually.
     * 
     * This method should be called on RouteBuilder instances to actually
     * register the route with the route registry.
     *
     * @param \Pollora\Route\Domain\Services\RouteBuilder $builder
     * @return void
     */
    public static function register(RouteBuilder $builder): void
    {
        $route = $builder->build();
        app(RouteRegistryInterface::class)->register($route);
    }
}
