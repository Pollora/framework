<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\Services\WordPress\Installation\LanguageService;
use Pollora\Services\WordPress\Installation\WordPressInstallLoaderService;
use Pollora\Support\Facades\Action;
use Pollora\WordPress\Commands\LaunchPadInstallCommand;
use Pollora\WordPress\Commands\LaunchPadSetupCommand;

class WordPressServiceProvider extends ServiceProvider
{
    protected Bootstrap $bootstrap;

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/wordpress.php', 'wordpress'
        );

        $this->app->singleton(Bootstrap::class);
        $this->bootstrap = $this->app->make(Bootstrap::class);
        $this->bootstrap->register();
        $this->app->singleton(DatabaseService::class);
        $this->app->singleton(InstallationService::class);
        $this->app->singleton(LanguageService::class);
        $this->app->singleton(WordPressInstallLoaderService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (is_secured()) {
            URL::forceScheme('https');
        }

        $this->bootstrap->boot();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/wordpress.php' => config_path('wordpress.php'),
            ], 'wp-config');

            $this->commands([
                LaunchPadSetupCommand::class,
                LaunchPadInstallCommand::class,
            ]);
        } else {
            Action::add('wp_install', function (): void {
                Artisan::call('migrate');
            });
        }
    }
}
