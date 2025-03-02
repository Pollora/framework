<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pollora\Route\Middleware\WordPressBindings;
use Pollora\Route\Middleware\WordPressBodyClass;
use Pollora\Route\Middleware\WordPressHeaders;

/**
 * Service provider for WordPress-specific routing functionalities.
 *
 * This provider extends Laravel's routing system with WordPress-specific
 * functionality without replacing the core routing components.
 */
class WordPressRouteServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend('router', function ($router, $app) {
            return new Router($app['events'], $app);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * Declares the 'wordpress' macro, enabling the definition of routes specific
     * to various WordPress content types (single, page, archive, etc.).
     * This macro functions similarly to the `any()` method but incorporates
     * WordPress-specific logic and automatically applies WordPress middleware.
     *
     * The macro accepts a variable number of arguments:
     * - First argument: WordPress condition (e.g., 'single', 'page')
     * - Last argument: Callback function or controller action
     * - Middle arguments (optional): Parameters for the WordPress condition
     *
     * @return void
     */
    public function boot(): void
    {
        Route::macro('wordpress', function (string $condition, ...$args) {
            if (empty($args)) {
                throw new \InvalidArgumentException('The wordpress route requires at least a condition and a callback.');
            }
            
            // First argument is the condition
            $uri = $condition;
            
            // Last argument is always the callback
            $action = $args[count($args) - 1];
            
            // Create the route
            $route = Route::addRoute(Router::$verbs, $uri, $action);
            $route->setIsWordPressRoute(true);
            
            // Extract condition parameters (all arguments except the last one)
            $conditionParams = [];
            if (count($args) > 1) {
                $conditionParams = array_slice($args, 0, count($args) - 1);
            }
            
            // Set condition parameters
            $route->setConditionParameters($conditionParams);
            
            // Add WordPress middleware
            $route->middleware([
                WordPressBindings::class,
                WordPressHeaders::class,
                WordPressBodyClass::class,
            ]);
            
            return $route;
        });
        
        // Add a shortcut 'wp' for the 'wordpress' macro
        Route::macro('wp', function (string $condition, ...$args) {
            return Route::wordpress($condition, ...$args);
        });
    }
}
