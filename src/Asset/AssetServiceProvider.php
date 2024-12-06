<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Support\ServiceProvider;
use Pollora\Foundation\Application;

class AssetServiceProvider extends ServiceProvider
{
    protected array $defaultAssetConfig = [
        'root' => 'assets',
        'images' => 'images',
        'fonts' => 'fonts',
        'css' => 'css',
        'js' => 'js',
    ];

    public function register(): void
    {
        $this->app->singleton('asset.container', fn ($app): AssetContainerManager => new AssetContainerManager($app));
        $this->app->singleton('wp.asset', fn ($app): AssetFactory => new AssetFactory($app));
        $this->app->singleton(ViteManager::class, function (Application $app): \Pollora\Asset\ViteManager {
            $defaultContainer = app('asset.container')->getDefault();

            return new ViteManager($defaultContainer);
        });
    }

    public function boot(): void
    {
        $this->registerViteManager();
    }

    protected function registerViteManager(): void
    {
        $this->app[ViteManager::class]->registerMacros();
    }
}
