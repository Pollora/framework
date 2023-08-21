<?php

declare(strict_types=1);

namespace Pollen\Ajax;

use Illuminate\Support\ServiceProvider;

class AjaxServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('wp.ajax', function ($app) {
            return new AjaxFactory();
        });
    }
}
