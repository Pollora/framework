<?php

declare(strict_types=1);

namespace Pollen\Route;

use Illuminate\Routing\RoutingServiceProvider;

class RouteServiceProvider extends RoutingServiceProvider
{
    public function registerRouter()
    {
        $this->app->singleton('router', function ($app) {
            return new Router($app['events'], $app);
        });
    }
}
