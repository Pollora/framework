<?php

declare(strict_types=1);

namespace Pollen\Admin;

use Illuminate\Support\ServiceProvider;

class PageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('wp.admin.page', fn($app): \Pollen\Admin\PageFactory => new PageFactory(new Page($app)));
    }
}
