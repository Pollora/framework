<?php

declare(strict_types=1);

namespace Pollora\WordPress\Config;

use Illuminate\Support\ServiceProvider;
use Pollora\WordPress\Config\ConstantManager;

class ConstantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('constant.manager', function () {
            return new ConstantManager();
        });
    }

    public function boot(): void
    {
        //
    }
}
