<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pollora\Route\Infrastructure\Middleware\WordPressBindings;
use Pollora\Route\Infrastructure\Middleware\WordPressBodyClass;
use Pollora\Route\Infrastructure\Middleware\WordPressHeaders;
use Pollora\Route\Infrastructure\Middleware\WordPressShutdown;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;
use Pollora\Route\Infrastructure\Services\WordPressConditionManager;
use Pollora\Route\UI\Http\Controllers\FrontendController;

/**
 * Service provider for WordPress-specific routing functionalities.
 *
 * This provider extends Laravel's routing system with WordPress-specific
 * functionality without replacing the core routing components.
 */
class RouteServiceProvider extends ServiceProvider
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
        // Register the WordPress type resolver
        $this->app->singleton(WordPressTypeResolverInterface::class, WordPressTypeResolver::class);

        // Register the condition manager (implements both interfaces)
        $this->app->singleton(WordPressConditionManagerInterface::class, function ($app) {
            return new WordPressConditionManager($app);
        });

        // Bind the domain interface to the same instance
        $this->app->bind(ConditionResolverInterface::class, function ($app) {
            return $app->make(WordPressConditionManagerInterface::class);
        });

        // Override the default router with our extended version
        $this->app->extend('router', function ($router, Application $app): ExtendedRouter {
            $logger = null;
            try {
                $logger = $app->make('log');
            } catch (\Exception) {
                // Logger not available
            }

            return new ExtendedRouter(
                $app->make('events'),
                $app,
                $app->make(WordPressConditionManagerInterface::class),
                $app->make(WordPressTypeResolverInterface::class),
                $logger
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * Declares the 'wordpress' macros, enabling the definition of routes specific
     * to various WordPress content types (single, page, archive, etc.).
     * These macros function similarly to the `any()` method but incorporate
     * WordPress-specific logic and automatically apply WordPress middleware.
     */
    public function boot(): void
    {
        $this->registerWpMatchMacro();
        $this->registerWpMacro();

        // Listen for the modules.routes.registered event to register fallback route
        Event::listen('modules.routes.registered', function () {
            $this->bootFallbackRoute();
        });

        // Fallback: if no modules are present, register the fallback route after boot
        $this->app->booted(function (): void {
            // Only register if the event hasn't been fired yet
            if (!$this->app->bound('route.fallback.registered')) {
                // Set a small delay to allow any potential module routes to be registered
                $this->app->afterResolving('router', function () {
                    if (!$this->app->bound('route.fallback.registered')) {
                        $this->bootFallbackRoute();
                        $this->app->instance('route.fallback.registered', true);
                    }
                });
            }
        });
    }

    /**
     * Register the wpMatch macro for specific HTTP verbs.
     */
    protected function registerWpMatchMacro(): void
    {
        Route::macro('wpMatch', function (array|string $methods, string $condition, ...$args) {
            if ($args === []) {
                throw new \InvalidArgumentException('The wp route requires at least a condition and a callback.');
            }

            // Get the router instance to resolve condition aliases
            $router = app('router');
            $resolvedCondition = $router->resolveCondition($condition);

            // Create a unique URI for the route
            $uri = $condition;
            if (! empty($args) && count($args) > 1) {
                // Hash the parameters to ensure uniqueness
                $paramHash = md5(serialize(array_slice($args, 0, -1)));
                $uri .= '_'.$paramHash;
            }

            // Last argument is always the callback
            $action = $args[count($args) - 1];

            // Create the route with specific HTTP methods
            $route = Route::addRoute($methods, $uri, $action);
            $route->setIsWordPressRoute(true);
            $route->setCondition($resolvedCondition);

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
    }

    /**
     * Register the wp macro as a shortcut for all HTTP verbs.
     */
    protected function registerWpMacro(): void
    {
        Route::macro('wp', fn (string $condition, ...$args) => Route::wpMatch(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $condition, ...$args)
        );
    }

    /**
     * Register the WordPress fallback route after all other routes.
     *
     * This method is called after module routes have been registered to ensure
     * it doesn't interfere with module route registration.
     */
    protected function bootFallbackRoute(): void
    {
        // Mark that the fallback route is being registered
        $this->app->instance('route.fallback.registered', true);

        // Add a catch-all route for WordPress templates (excluding API routes)
        Route::any('{any}', [FrontendController::class, 'handle'])
            ->where('any', '^(?!api/).*')
            ->middleware([
                WordPressBindings::class,
                WordPressHeaders::class,
                WordPressBodyClass::class,
                WordPressShutdown::class,
            ]);
    }
}
