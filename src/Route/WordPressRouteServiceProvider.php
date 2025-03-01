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
     * @return void
     */
    public function boot(): void
    {
        Route::macro('wordpress', function ($uri, $action = null, array $parameters = []) {
            $route = Route::addRoute(Router::$verbs, $uri, $action);
            
            $route->setIsWordPressRoute(true);

            // Ajouter automatiquement les middlewares WordPress
            $route->middleware([
                WordPressBindings::class,
                WordPressHeaders::class,
                WordPressBodyClass::class,
            ]);

            return $route;
        });
    }
}
