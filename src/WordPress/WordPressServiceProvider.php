<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Hook\Domain\Contracts\Action;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\Services\WordPress\Installation\LanguageService;
use Pollora\Services\WordPress\Installation\WordPressInstallLoaderService;
use Pollora\WordPress\Commands\LaunchPadInstallCommand;
use Pollora\WordPress\Commands\LaunchPadSetupCommand;

class WordPressServiceProvider extends ServiceProvider
{
    protected Bootstrap $bootstrap;

    protected ConsoleDetectionService $consoleDetectionService;

    public function __construct($app, ?ConsoleDetectionService $consoleDetectionService = null)
    {
        parent::__construct($app);
        $this->consoleDetectionService = $consoleDetectionService ?? resolve(ConsoleDetectionService::class);
    }

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
        $this->handleHttpsProtocol();

        $this->bootstrap->boot();

        if ($this->consoleDetectionService->isConsole()) {
            $this->publishes([
                __DIR__.'/../../config/wordpress.php' => config_path('wordpress.php'),
            ], 'wordpress');

            $this->commands([
                LaunchPadSetupCommand::class,
                LaunchPadInstallCommand::class,
            ]);
        } else {
            /** @var Action $action */
            $action = $this->app->make(Action::class);
            $action->add('wp_install', function (): void {
                Artisan::call('migrate');
            });
        }
    }

    /**
     * Force HTTPS protocol handling for WordPress requests.
     *
     * Proxy header handling (X-Forwarded-Proto) is delegated to Laravel's
     * TrustProxies middleware which should be configured in the application.
     */
    private function handleHttpsProtocol(): void
    {
        if (is_secured()) {
            URL::forceScheme('https');
            $_SERVER['HTTPS'] = 'on';
        }
    }
}
