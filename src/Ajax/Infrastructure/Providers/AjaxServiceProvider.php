<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Ajax\Adapter\Out\WordPress\ScriptInjectionAdapter;
use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Factory\AjaxFactory;
use Pollora\Ajax\Infrastructure\Services\AjaxDiscovery;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;
use Pollora\Attributes\Ajax;

/**
 * Laravel service provider that bridges the `pollora/ajax` package into the framework.
 *
 * Wires the package's hexagonal components (port, service, factory) into the
 * Laravel service container, registers the `#[Ajax]` attribute discovery,
 * and boots the frontend AJAX URL script injection.
 *
 * Bindings:
 *  - {@see AjaxActionRegistrarPort} → {@see WordPressAjaxActionRegistrar} (singleton)
 *  - {@see RegisterAjaxActionService} (singleton)
 *  - `wp.ajax` → {@see AjaxFactory} (singleton, used by the Ajax facade)
 *  - {@see AjaxDiscovery} (singleton, plugged into the discovery engine)
 *
 * @see \Pollora\Support\Facades\Ajax  The Laravel facade resolved via `wp.ajax`.
 * @see Ajax       The PHP attribute discovered by this provider.
 */
class AjaxServiceProvider extends ServiceProvider
{
    /**
     * Register AJAX bindings into the container.
     */
    public function register(): void
    {
        $this->app->singleton(AjaxActionRegistrarPort::class, WordPressAjaxActionRegistrar::class);
        $this->app->singleton(RegisterAjaxActionService::class, fn (Application $app): RegisterAjaxActionService => new RegisterAjaxActionService($app->make(AjaxActionRegistrarPort::class)));
        $this->app->singleton('wp.ajax', fn (Application $app): AjaxFactory => new AjaxFactory($app->make(RegisterAjaxActionService::class)));

        $this->app->singleton(AjaxDiscovery::class, fn (Application $app): AjaxDiscovery => new AjaxDiscovery(
            $app->make('wp.ajax')
        ));
    }

    /**
     * Boot the AJAX services.
     *
     * Injects the `Pollora.ajaxurl` JavaScript global via `wp_head`.
     */
    public function boot(): void
    {
        (new ScriptInjectionAdapter)->registerAjaxUrlScript();
    }
}
