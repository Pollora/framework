<?php

declare(strict_types=1);

/**
 * Class ThemeInitializer
 *
 * This class is responsible for initializing the theme and registering the theme provider.
 */

namespace Pollen\Theme;

use Pollen\Support\Facades\Action;
use Qirolab\Theme\Theme;

/**
 * Class ThemeInitializer
 *
 * The ThemeInitializer class is responsible for initializing and configuring the theme.
 */
class ThemeInitializer
{
    protected $themeRoot;

    public function __construct(protected ?ThemeServiceProvider $themeProvider)
    {
        $this->themeRoot = config('theme.base_path');
    }

    public function init()
    {
        $this->theme_root = config('theme.base_path');
        Action::add('after_setup_theme', function () {
            if (wp_installing()) {
                return;
            }
            $this->initializeTheme();
        }, 1);
    }

    /**
     * Initializes the theme.
     */
    private function initializeTheme()
    {
        global $wp_theme_directories;

        register_theme_directory($this->theme_root);

        $this->setThemes();
        $this->registerThemeProvider();

        $GLOBALS['wp_theme_directories'][] = WP_CONTENT_DIR.'/themes';

        $this->wp_theme = wp_get_theme();

        $this->themeProvider->singleton('wp.theme', function () {
            return $this->wp_theme;
        });
    }

    /**
     * Registers all the theme providers.
     *
     * @return void
     */
    private function registerThemeProvider()
    {
        foreach (config('theme.providers') as $provider) {
            $this->themeProvider->registerProvider($provider);
        }
    }

    /**
     * Sets up the theme and its configuration.
     */
    public function setThemes()
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

        if (! app()->configurationIsCached()) {
            foreach ($themeConfigs as $themeConfig) {
                if (! $this->isThemeIdentical($childTheme)) {
                    $this->themeProvider->registerThemeConfig(get_stylesheet_directory()."/config/{$themeConfig}.php", "theme.{$themeConfig}");
                }
                $this->themeProvider->registerThemeConfig(get_stylesheet_directory()."/config/{$themeConfig}.php", "theme.{$themeConfig}");
            }
        }
    }

    /**
     * Check if the child theme is identical to the parent theme.
     *
     * @param  string  $childTheme The name of the child theme to check.
     * @return bool Returns true if the child theme is identical to the parent theme, false otherwise.
     */
    public function isThemeIdentical($childTheme)
    {
        return get_template() === $childTheme;
    }
}
