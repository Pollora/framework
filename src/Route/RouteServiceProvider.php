<?php

declare(strict_types=1);

namespace Pollen\Route;

use Illuminate\Routing\RoutingServiceProvider;
use Pollen\Foundation\Application;

class RouteServiceProvider extends RoutingServiceProvider
{
    public function registerRouter(): void
    {
        $this->app->singleton('router', fn(Application $app): \Pollen\Route\Router => new Router($app['events'], $app));
    }
}
