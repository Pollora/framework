<?php

declare(strict_types=1);

namespace Pollora\Ajax;

use Illuminate\Support\ServiceProvider;

class AjaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('wp.ajax', fn ($app): \Pollora\Ajax\AjaxFactory => new AjaxFactory);
    }
}
