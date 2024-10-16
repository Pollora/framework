<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Support\ServiceProvider;
use Pollen\Asset\Vite;
use Pollen\Support\Facades\Theme;
use Illuminate\Support\Facades\Vite as ViteFacade;

class AssetServiceProvider extends ServiceProvider
{
    protected array $defaultAssetConfig = [
        'root' => 'assets',
        'images' => 'images',
        'fonts' => 'fonts',
        'css' => 'css',
        'js' => 'js',
    ];

    /**
     * Registers the asset service provider.
     *
     * This method registers the necessary services for handling assets in the application.
     * It sets up the asset container manager, Vite instance, and asset factory as singletons.
     */
    public function register(): void
    {
        $this->app->singleton('asset.container', fn($app): \Pollen\Asset\AssetContainerManager => new AssetContainerManager($app));

        $this->app->singleton('wp.vite', fn($app): \Pollen\Asset\Vite => new Vite($app));

        $this->app->singleton('wp.asset', fn($app): \Pollen\Asset\AssetFactory => new AssetFactory($app));
    }

    /**
     * Bootstraps the asset service provider.
     *
     * This method sets up the asset configuration and registers the asset macros.
     * It creates a singleton for the asset configuration and merges the default asset configuration with the theme's asset directory.
     * It also registers the asset macros for handling different types of assets like images, fonts, CSS, and JavaScript.
     */
    public function boot(): void
    {
        $this->app->singleton('wp.asset.config', fn(): array => $this->mergeAssetConfig());
        $this->registerAssetMacros();
    }

    /**
     * Merges the default asset configuration with the theme's asset directory.
     *
     * This method merges the default asset configuration array with the theme's asset directory configuration.
     * If the theme's asset directory is empty, the default asset configuration is returned.
     *
     * @return array The merged asset configuration array.
     */
    protected function mergeAssetConfig(): array
    {
        return array_merge(
            $this->defaultAssetConfig,
            config('theme.asset_dir', [])
        );
    }

    /**
     * Registers the asset macros for handling different types of assets.
     *
     * This method registers the asset macros for handling different types of assets like images, fonts, CSS, and JavaScript.
     * It uses the Vite facade to define macros for each asset type, which allows for easy retrieval of assets using Laravel's Blade templating engine.
     */
    protected function registerAssetMacros(): void
    {
        $assetTypes = ['image' => 'images', 'font' => 'fonts', 'css' => 'css', 'js' => 'js'];

        foreach ($assetTypes as $macroName => $assetType) {
            ViteFacade::macro($macroName, function (string $path, ?string $container = null) use ($assetType) {
                $asset = app('wp.vite');
                return $asset->retrieveAsset($path, $assetType, $container);
            });
        }
    }

}
