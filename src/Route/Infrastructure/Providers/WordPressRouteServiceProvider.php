<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pollora\Http\Controllers\FrontendController;
use Pollora\Route\Infrastructure\Adapters\LaravelBodyClassMiddleware;
use Pollora\Route\Infrastructure\Adapters\LaravelHeadersMiddleware;
use Pollora\Route\Infrastructure\Adapters\LaravelRouteBindingMiddleware;
use Pollora\Route\Infrastructure\Adapters\LaravelShutdownMiddleware;
use Pollora\Route\Infrastructure\Adapters\Router;

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
     */
    public int $priority = -99;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the CallableDispatcher, which is needed by the Router
        $this->app->singleton(CallableDispatcherContract::class, CallableDispatcher::class);
        
        // Explicitly instantiate the custom Router with all required dependencies
        $this->app->singleton('router', function ($app) {
            $events = $app->make(Dispatcher::class);
            $router = new Router($events, $app);
            
            // Make sure any Laravel Router setup is applied to our custom Router
            if (method_exists($router, 'setContainer')) {
                $router->setContainer($app);
            }
            
            return $router;
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
     */
    public function boot(): void
    {
        // Add the wpMatch macro for specific HTTP verbs
        Route::macro('wpMatch', function (array|string $methods, string $condition, ...$args) {
            if ($args === []) {
                throw new \InvalidArgumentException('The wp route requires at least a condition and a callback.');
            }

            // Last argument is always the callback
            $action = $args[count($args) - 1];

            // Extract condition parameters (all arguments except the last one)
            $conditionParams = [];
            if (count($args) > 1) {
                $conditionParams = array_slice($args, 0, count($args) - 1);
            }

            // Create a unique URI that incorporates both the condition and parameters
            // This ensures that routes with the same condition but different parameters
            // are treated as distinct routes
            $uri = $condition;

            // Only modify URI if we have parameters
            if (! empty($conditionParams)) {
                // Create a unique suffix based on the parameters
                $paramSuffix = '_'.md5(serialize($conditionParams));
                $uri = $condition.$paramSuffix;
            }

            // Create the route with specific HTTP methods
            $route = Route::addRoute($methods, $uri, $action);
            $route->setIsWordPressRoute(true);

            // Set condition parameters
            $route->setConditionParameters($conditionParams);

            // Add WordPress middleware
            $route->middleware([
                LaravelBodyClassMiddleware::class,
                LaravelRouteBindingMiddleware::class,
                LaravelShutdownMiddleware::class,
                LaravelHeadersMiddleware::class,
            ]);

            return $route;
        });

        // Add the wp macro as a shortcut for all HTTP verbs
        Route::macro('wp', fn (string $condition, ...$args) => Route::wpMatch(Router::$verbs, $condition, ...$args));

        $this->app->booted(function (): void {
            $this->bootFallbackRoute();
        });
    }

    /**
     * Register the WordPress fallback route after all other routes.
     */
    protected function bootFallbackRoute(): void
    {
        // Add a catch-all route for WordPress templates
        Route::any('{any}', [FrontendController::class, 'handle'])
            ->where('any', '.*')
            ->middleware([
                LaravelBodyClassMiddleware::class,
                LaravelRouteBindingMiddleware::class,
                LaravelShutdownMiddleware::class,
                LaravelHeadersMiddleware::class,
            ]);
    }
}
