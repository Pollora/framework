<?php

declare(strict_types=1);

namespace Pollora\Theme;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Theme;
use Pollora\Theme\Contracts\ThemeComponent;
use Pollora\Support\Facades\Filter;

class ThemeInitializer implements ThemeComponent
{
    protected $themeRoot;

    protected $wp_theme;

    public function __construct(protected Application $app)
    {
        $this->themeRoot = config('theme.base_path');
    }

    public function register(): void
    {
        Action::add('after_setup_theme', function (): void {
            if (wp_installing()) {
                return;
            }
            $this->initializeTheme();
        }, 1);

        $this->overrideThemeUri();
    }

    private function initializeTheme(): void
    {
        global $wp_theme_directories;

        register_theme_directory($this->themeRoot);

        $this->setThemes();
        $this->registerThemeProvider();

        $GLOBALS['wp_theme_directories'][] = WP_CONTENT_DIR.'/themes';

        $this->wp_theme = wp_get_theme();

        $this->app->singleton('wp.theme', fn() => $this->wp_theme);
    }

    private function registerThemeProvider(): void
    {
        foreach ((array) config('theme.providers') as $provider) {
            $this->app->register($provider);
        }
    }

    public function setThemes(): void
    {
        $childTheme = get_stylesheet();

        Theme::load($childTheme);

        $themeConfigs = [
            'supports',
            'menus',
            'templates',
            'sidebars',
            'images',
            'gutenberg',
            'providers',
        ];

        if (! $this->app->configurationIsCached()) {
            foreach ($themeConfigs as $themeConfig) {
                $this->mergeConfigFrom(Theme::path("config/{$themeConfig}.php"), "theme.{$themeConfig}");
            }
        }
    }

    public function isThemeIdentical($childTheme): bool
    {
        return get_template() === $childTheme;
    }

    protected function mergeConfigFrom($path, $key): void
    {
        $config = $this->app['config']->get($key, []);
        if (!file_exists($path)) {
            return;
        }
        $this->app['config']->set($key, array_merge(require $path, $config));
    }

    protected function overrideThemeUri(): void
    {
        Filter::add('theme_file_uri', function ($uri): string {
            $assetConfig = $this->app['asset.container']->get('theme')->getAssetDir();
            $rootDir = $assetConfig['root'];
            $relativePath = $this->getRelativePath($uri, $rootDir);
            return app('wp.vite')->retrieveAsset($relativePath, '', 'theme');
        });
    }

    protected function getRelativePath(string $uri, string $rootDir): string
    {
        return str_replace(get_stylesheet_directory_uri() . '/' . $rootDir . '/', '', $uri);
    }
}
