<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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
     * WordPress-specific logic.
     *
     * @return void
     */
    public function boot(): void
    {
        Route::macro('wordpress', function ($uri, $action = null, array $parameters = []) {
            return Route::addRoute(Router::$verbs, $uri, $action);
        });
    }
}
