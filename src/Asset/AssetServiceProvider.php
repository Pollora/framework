<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Contracts\Container\BindingResolutionException;
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
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->app->singleton(AssetContainerManager::class, fn ($app) => new AssetContainerManager($app));
        $this->app->singleton(AssetFactory::class, fn ($app) => new AssetFactory($app));

        $containerManager = $this->app->make(AssetContainerManager::class);
        $containerManager->addContainer('root', []);
        $containerManager->setDefaultContainer('root');

        $this->app->singleton(ViteManager::class, fn (Application $app) => new ViteManager($containerManager->getDefault()));
    }

    /**
     * Bootstrap asset services.
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->registerViteManager();
    }

    /**
     * Register Vite-specific functionality.
     * @throws BindingResolutionException
     */
    protected function registerViteManager(): void
    {
        $this->app->make(ViteManager::class)->registerMacros();
    }
}
