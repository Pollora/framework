<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Infrastructure\Middleware\WordPressBindings;
use Pollora\Route\Infrastructure\Middleware\WordPressBodyClass;
use Pollora\Route\Infrastructure\Middleware\WordPressHeaders;
use Pollora\Route\Infrastructure\Middleware\WordPressShutdown;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;
use Pollora\Route\Infrastructure\Services\WordPressConditionManager;
use Pollora\Route\Infrastructure\Services\WordPressRoutingService;
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
     * WordPress middleware stack applied to all WordPress routes.
     */
    public const WORDPRESS_MIDDLEWARE = [
        WordPressBindings::class,
        WordPressHeaders::class,
        WordPressBodyClass::class,
        WordPressShutdown::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the WordPress type resolver
        $this->app->singleton(WordPressTypeResolverInterface::class, WordPressTypeResolver::class);

        // Register the condition manager (implements both interfaces)
        $this->app->singleton(WordPressConditionManagerInterface::class, fn ($app): WordPressConditionManager => new WordPressConditionManager($app));

        // Bind the domain interface to the same instance
        $this->app->bind(ConditionResolverInterface::class, fn ($app) => $app->make(WordPressConditionManagerInterface::class));

        // Register the routing service (encapsulates condition resolution and type binding)
        $this->app->singleton(WordPressRoutingService::class, function ($app): WordPressRoutingService {
            $logger = null;
            try {
                $logger = $app->make('log');
            } catch (\Exception) {
                // Logger not available during early bootstrap
            }

            return new WordPressRoutingService(
                $app->make(WordPressConditionManagerInterface::class),
                $app->make(WordPressTypeResolverInterface::class),
                $logger
            );
        });

        // Override the default router with our extended version (for custom Route model)
        $this->app->extend('router', function ($router, Application $app): ExtendedRouter {
            return new ExtendedRouter(
                $app->make('events'),
                $app,
                $app->make(WordPressConditionManagerInterface::class),
            );
        });

        // Register WordPress types in the container for dependency injection
        $this->app->booted(function (): void {
            $this->app->make(WordPressRoutingService::class)->registerWordPressTypes($this->app);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * Declares the 'wordpress' macros, enabling the definition of routes specific
     * to various WordPress content types (single, page, archive, etc.).
     */
    public function boot(): void
    {
        $this->registerWpMatchMacro();
        $this->registerWpMacro();

        // Register fallback route after modules have loaded their routes.
        // Two triggers ensure it works with or without the modules system:
        // 1. Event from ModuleServiceProvider (when modules are present)
        // 2. Booted callback (when no modules, or event wasn't dispatched)
        Event::listen('modules.routes.registered', fn () => $this->bootFallbackRoute());

        $this->app->booted(fn () => $this->bootFallbackRoute());
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

            // Resolve condition alias via the routing service
            $resolvedCondition = app(WordPressRoutingService::class)->resolveCondition($condition);

            // Create a unique URI for the route
            $uri = $condition;
            if (count($args) > 1) {
                $paramHash = md5(serialize(array_slice($args, 0, -1)));
                $uri .= '_' . $paramHash;
            }

            // Last argument is always the callback
            $action = $args[count($args) - 1];

            // Create the route with specific HTTP methods
            $route = Route::addRoute($methods, $uri, $action);
            $route->setIsWordPressRoute(true);
            $route->setCondition($resolvedCondition);

            // Extract condition parameters (all arguments except the last one)
            if (count($args) > 1) {
                $route->setConditionParameters(array_slice($args, 0, count($args) - 1));
            }

            // Add WordPress middleware
            $route->middleware(RouteServiceProvider::WORDPRESS_MIDDLEWARE);

            return $route;
        });
    }

    /**
     * Register the wp macro as a shortcut for all HTTP verbs.
     */
    protected function registerWpMacro(): void
    {
        Route::macro('wp', fn (string $condition, ...$args) => Route::wpMatch(
            ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
            $condition,
            ...$args
        ));
    }

    /**
     * Register the WordPress fallback route after all other routes.
     */
    protected function bootFallbackRoute(): void
    {
        $this->app->instance('route.fallback.registered', true);

        Route::any('{any}', [FrontendController::class, 'handle'])
            ->where('any', '^(?!api/).*')
            ->middleware(self::WORDPRESS_MIDDLEWARE);
    }
}