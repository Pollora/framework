<?php

declare(strict_types=1);

namespace Pollora\Foundation\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Admin\Page;
use Pollora\Admin\PageFactory;
use Pollora\Foundation\Console\Commands\MakeModelCommand;

/**
 * Service provider for admin pages and artisan commands.
 */
class ArtisanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin page factory
        $this->app->singleton(
            'wp.admin.page',
            fn ($app): PageFactory => new PageFactory(new Page($app))
        );

        // Console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModelCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../Console/pollora.stub' => $this->app->basePath('pollora'),
        ], 'pollora-binary');

        $this->publishes([
            __DIR__.'/../Console/ddev-pollora.stub' => $this->app->basePath('.ddev/commands/web/pollora'),
        ], 'pollora-ddev');
    }
}
