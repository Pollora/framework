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
        $this->app->singleton('wp.asset.config', fn (): array => $this->mergeAssetConfig());
        $this->registerViteManager();
    }

    protected function mergeAssetConfig(): array
    {
        return array_merge(
            $this->defaultAssetConfig,
            config('theme.asset_dir', [])
        );
    }

    protected function registerViteManager(): void
    {
        $this->app[ViteManager::class]->registerMacros();
    }

    public function retrieveAsset(string $path, string $assetType = '', ?string $assetContainer = null): string
    {
        $container = $assetContainer !== null && $assetContainer !== '' && $assetContainer !== '0'
            ? $this->app['asset.container']->get($assetContainer)
            : null;

        if ($container) {
            $assetConfig = $container->getAssetDir();
            $rootDir = $assetConfig['root'];
            $assetTypeDir = $assetConfig[$assetType] ?? '';

            $prefix = $this->buildAssetPrefix($rootDir, $assetTypeDir);
            $path = $prefix.$path;
        }

        return $this->buildViteAsset($path, $container);
    }

    protected function buildViteAsset(string $path, ?AssetContainer $container = null): string
    {
        $viteManager = $this->app[ViteManager::class];

        if ($container instanceof \Pollora\Asset\AssetContainer) {
            $viteManager->configureVite();
        }

        return $viteManager->asset($path);
    }

    protected function buildAssetPrefix(string $rootDir, string $assetTypeDir): string
    {
        return $assetTypeDir !== '' && $assetTypeDir !== '0' ? "{$rootDir}/{$assetTypeDir}/" : "{$rootDir}/";
    }
}
