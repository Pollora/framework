<?php

declare(strict_types=1);

namespace Pollora\WordPress\Config;

use Illuminate\Support\ServiceProvider;

class ConstantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('constant.manager', fn (): ConstantManager => new ConstantManager);
    }

    public function boot(): void
    {
        //
    }
}
