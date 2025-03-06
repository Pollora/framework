<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for registering asset-related services.
 *
 * This provider registers and bootstraps all asset management services,
 * including container management, asset factory, and Vite integration.
 */
class AssetServiceProvider extends ServiceProvider
{
    /**
     * Register asset-related services in the container.
     */
    public function register(): void
    {

        $this->app->singleton('asset.container', fn ($app): AssetContainerManager => new AssetContainerManager($app));

        $this->app['asset.container']->addContainer('root', []);
        $this->app['asset.container']->setDefaultContainer('root');

        $this->app->singleton('wp.asset', fn ($app): AssetFactory => new AssetFactory($app));
        $this->app->singleton(ViteManager::class, function (Application $app): \Pollora\Asset\ViteManager {
            $defaultContainer = app('asset.container')->getDefault();

            return new ViteManager($defaultContainer);
        });
    }

    /**
     * Bootstrap asset services.
     */
    public function boot(): void
    {
        $this->registerViteManager();
    }

    /**
     * Register Vite-specific functionality.
     */
    protected function registerViteManager(): void
    {
        app(ViteManager::class)->registerMacros();
    }
}
