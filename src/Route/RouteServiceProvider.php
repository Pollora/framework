<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Routing\RoutingServiceProvider;
use Pollora\Foundation\Application;

class RouteServiceProvider extends RoutingServiceProvider
{
    public function registerRouter(): void
    {
        $this->app->singleton('router', fn(Application $app): \Pollora\Route\Router => new Router($app['events'], $app));
    }
}
