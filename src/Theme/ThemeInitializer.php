<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Illuminate\Contracts\Foundation\Application;
use Pollen\Support\Facades\Action;
use Pollen\Theme\Contracts\ThemeComponent;

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
        Action::add('after_setup_theme', function () {
            if (wp_installing()) {
                return;
            }
            $this->initializeTheme();
        }, 1);
    }

    private function initializeTheme(): void
    {
        global $wp_theme_directories;

        register_theme_directory($this->themeRoot);

        $this->setThemes();
        $this->registerThemeProvider();

        $GLOBALS['wp_theme_directories'][] = WP_CONTENT_DIR.'/themes';

        $this->wp_theme = wp_get_theme();

        $this->app->singleton('wp.theme', function () {
            return $this->wp_theme;
        });
    }

    private function registerThemeProvider(): void
    {
        foreach (config('theme.providers') as $provider) {
            $this->app->register($provider);
        }
    }

    public function setThemes(): void
    {
        $childTheme = get_stylesheet();
        $parentTheme = $this->isThemeIdentical($childTheme) ? null : get_template();

        Theme::set($childTheme, $parentTheme);

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
        $this->app['config']->set($key, array_merge(require $path, $config));
    }
}
