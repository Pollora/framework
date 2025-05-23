<?php

declare(strict_types=1);

namespace Pollora\WordPress;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Hook\Infrastructure\Services\Action;
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
        $this->consoleDetectionService = $consoleDetectionService ?? app(ConsoleDetectionService::class);
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
            ], 'wp-config');

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
     */
    private function handleHttpsProtocol(): void
    {
        if (is_secured()) {
            URL::forceScheme('https');
        }

        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['X_FORWARDED_PROTO'] ?? null;

        if ($forwardedProto) {
            $this->setHttpsBasedOnProxy($forwardedProto);
        }
    }

    /**
     * Determines whether HTTPS should be forced based on proxy headers.
     */
    private function setHttpsBasedOnProxy(string $forwardedProtocols): void
    {
        $protocols = array_map('trim', explode(',', $forwardedProtocols));

        // Check if the last protocol is HTTPS
        $_SERVER['HTTPS'] = end($protocols) === 'https' ? 'on' : 'off';
    }
}
