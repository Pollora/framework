<?php

declare(strict_types=1);

namespace Pollen\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pollen\WordPress\Bootstrap;

class WordPressServiceProvider extends ServiceProvider
{
    protected Bootstrap $bootstrap;

    public function register(): void
    {
        $this->app->singleton(Bootstrap::class);
        $this->bootstrap = $this->app->make(Bootstrap::class);
        $this->bootstrap->register();
    }

    public function boot(): void
    {
        if (is_secured()) {
            URL::forceScheme('https');
        }

        $this->bootstrap->boot();
    }
}
