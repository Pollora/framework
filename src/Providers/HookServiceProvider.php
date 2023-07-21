<?php

namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Pollen\Hook\ActionBuilder;
use Pollen\Hook\FilterBuilder;

class HookServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('action', function ($container) {
            return new ActionBuilder($container);
        });

        $this->app->bind('filter', function ($container) {
            return new FilterBuilder($container);
        });
    }
}
