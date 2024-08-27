<?php

declare(strict_types=1);

namespace Pollen\Admin;

use Illuminate\Support\ServiceProvider;

class PageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('wp.admin.page', function ($app) {
            return new PageFactory(new Page($app));
        });
    }
}
