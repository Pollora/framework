<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Routing\RoutingServiceProvider;
use Pollora\Foundation\Application;

/**
 * Service provider for WordPress-specific routing functionality.
 *
 * This provider extends Laravel's RoutingServiceProvider to register
 * a custom Router implementation that handles WordPress-specific routing needs.
 */
class RouteServiceProvider extends RoutingServiceProvider
{
    /**
     * Register the WordPress-aware Router implementation.
     *
     * Binds a singleton instance of the WordPress-compatible Router
     * to the application container.
     */
    public function registerRouter(): void
    {
        $this->app->singleton('router', fn (Application $app): \Pollora\Route\Router => new Router($app['events'], $app));
    }
}
