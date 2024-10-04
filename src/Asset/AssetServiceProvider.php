<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Theme;

class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('asset.container', fn($app): \Pollen\Asset\AssetContainerManager => new AssetContainerManager($app));

        $this->app->singleton('wp.vite', fn($app): \Pollen\Asset\Vite => new Vite($app));

        $this->app->singleton('wp.asset', fn($app): \Pollen\Asset\AssetFactory => new AssetFactory($app));
    }

    public function boot(): void
    {
        $theme = Theme::active();
        $this->app['asset.container']->addContainer('theme', [
            'hot_file' => public_path("{$theme}.hot"),
            'build_directory' => "build/{$theme}",
            'manifest_path' => public_path("build/{$theme}/manifest.json"),
            'base_path' => '',
        ]);

        $this->app['asset.container']->setDefaultContainer('theme');
    }
}
