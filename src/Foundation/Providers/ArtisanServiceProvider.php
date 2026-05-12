<?php

declare(strict_types=1);

namespace Pollora\Foundation\Providers;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;
use Pollora\Admin\Page;
use Pollora\Admin\PageFactory;
use Pollora\Foundation\Console\Commands\MakeModelCommand;
use Pollora\Foundation\Console\PolloraBinaryManager;

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

        $this->registerPolloraBinary();
    }

    public function boot(): void
    {
        $this->publishPolloraBinary();
    }

    protected function registerPolloraBinary(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Artisan::starting(static function (Artisan $artisan): void {
            PolloraBinaryManager::boot($artisan);
        });
    }

    protected function publishPolloraBinary(): void
    {
        $this->publishes([
            __DIR__.'/../Console/pollora.stub' => $this->app->basePath('pollora'),
        ], 'pollora-binary');
    }
}
