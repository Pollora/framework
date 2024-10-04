<?php

declare(strict_types=1);

namespace Pollen\Ajax;

use Illuminate\Support\ServiceProvider;

class AjaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('wp.ajax', fn($app): \Pollen\Ajax\AjaxFactory => new AjaxFactory);
    }
}
