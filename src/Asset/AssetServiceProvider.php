<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Support\ServiceProvider;
use Pollen\Foundation\Application;

class AssetServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('wp.vite', function (Application $app) {
            return new Vite($app);
        });

        $this->app->singleton('wp.asset', function ($app) {
            return new AssetFactory;
        });
    }
}
