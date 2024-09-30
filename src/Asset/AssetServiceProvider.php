<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Theme;

class AssetServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('asset.container', function ($app) {
            return new AssetContainerManager($app);
        });

        $this->app->singleton('wp.vite', function ($app) {
            return new Vite($app);
        });

        $this->app->singleton('wp.asset', function ($app) {
            return new AssetFactory($app);
        });
    }

    public function boot()
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
