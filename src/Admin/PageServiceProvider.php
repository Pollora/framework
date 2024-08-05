<?php
declare(strict_types=1);

namespace Pollen\Admin;

use Illuminate\Support\ServiceProvider;
use Pollen\Admin\Contracts\PageInterface;

class PageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('wp.admin.page', function ($app) {
            return new PageFactory(new Page($app));
        });
    }
}
