<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pollora\Route\Middleware\WordPressBindings;
use Pollora\Route\Middleware\WordPressBodyClass;
use Pollora\Route\Middleware\WordPressHeaders;
use Pollora\Http\Controllers\FrontendController;
use Pollora\Route\Middleware\WordPressShutdown;
/**
 * Service provider for WordPress-specific routing functionalities.
 *
 * This provider extends Laravel's routing system with WordPress-specific
 * functionality without replacing the core routing components.
 */
class WordPressRouteServiceProvider extends ServiceProvider
{
    /**
     * The priority level of the service provider.
     * A lower priority means it will be loaded later.
     *
     * @var int
     */
    public $priority = -99;

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
        // Add the wpMatch macro for specific HTTP verbs
        Route::macro('wpMatch', function (array|string $methods, string $condition, ...$args) {
            if (empty($args)) {
                throw new \InvalidArgumentException('The wp route requires at least a condition and a callback.');
            }

            // First argument is the condition
            $uri = $condition;

            // Last argument is always the callback
            $action = $args[count($args) - 1];

            // Create the route with specific HTTP methods
            $route = Route::addRoute($methods, $uri, $action);
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
                WordPressShutdown::class,
            ]);

            return $route;
        });

        // Add the wp macro as a shortcut for all HTTP verbs
        Route::macro('wp', function (string $condition, ...$args) {
            return Route::wpMatch(Router::$verbs, $condition, ...$args);
        });

        $this->app->booted(function () {
            $this->bootFallbackRoute();
        });
    }

    /**
     * Register the WordPress fallback route after all other routes.
     *
     * @return void
     */
    protected function bootFallbackRoute(): void
    {
        // Add a catch-all route for WordPress templates
        Route::any('{any}', [FrontendController::class, 'handle'])
            ->where('any', '.*')
            ->middleware([
                WordPressBindings::class,
                WordPressHeaders::class,
                WordPressBodyClass::class,
                WordPressShutdown::class,
            ]);
    }
}
