<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Pollora\Asset\Domain\Models\AssetFile;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Domain\Support\ThemeConfig;

class ThemeInitializer implements ThemeComponent
{
    protected $themeRoot;

    protected $wp_theme;

    protected Action $action;

    protected Filter $filter;

    protected ?ThemeService $themeService = null;

    protected WordPressThemeInterface $wpTheme;

    /**
     * Create a new theme initializer
     */
    public function __construct(
        protected ContainerInterface $app,
        protected ConfigRepositoryInterface $config
    ) {
        $this->themeRoot = ThemeConfig::get('theme.base_path');
        $this->action = $this->app->get(Action::class);
        $this->filter = $this->app->get(Filter::class);
        $this->wpTheme = $this->app->get(WordPressThemeInterface::class);

        $this->filter->add('stylesheet_directory', $this->overrideStylesheetDirectory(...), 90, 3);
    }

    /**
     * Get the ThemeService, resolving it if not already done
     */
    protected function getThemeService(): ThemeService
    {
        if ($this->themeService === null) {
            $this->themeService = $this->app->get(ThemeService::class);

            // Fallback to 'theme' binding if ThemeService interface isn't registered yet
            if ($this->themeService === null) {
                $this->themeService = $this->app->get('theme');
            }

            if ($this->themeService === null) {
                throw new \RuntimeException('Unable to resolve ThemeService. Make sure it is properly registered.');
            }
        }

        return $this->themeService;
    }

    /**
     * Register the theme
     */
    public function register(): void
    {
        $this->action->add('after_setup_theme', function (): void {
            // Use the interface instead of direct function call
            if ($this->wpTheme->isInstalling()) {
                return;
            }
            $this->initializeTheme();
        }, 1);

        $this->overrideThemeUri();
    }

    /**
     * Override the stylesheet directory URI
     */
    public function overrideStylesheetDirectory(string $stylesheetDirUri, string $stylesheet, string $themeRootUri): string
    {
        return str_replace($themeRootUri, ThemeConfig::get('theme.base_path'), $stylesheetDirUri);
    }

    /**
     * Initialize the theme
     */
    private function initializeTheme(): void
    {
        // Use the interface instead of direct function call
        $this->wpTheme->registerThemeDirectory($this->themeRoot);

        $this->setThemes();
        $this->registerThemeProvider();

        // Use the interface to get theme directories
        $directories = $this->wpTheme->getThemeDirectories();
        if (! empty($directories)) {
            if (isset($GLOBALS['wp_theme_directories'])) {
                $GLOBALS['wp_theme_directories'] = array_merge(
                    $GLOBALS['wp_theme_directories'],
                    $directories
                );
            } else {
                $GLOBALS['wp_theme_directories'] = $directories;
            }
        }

        // Use the interface to get the theme instance
        $this->wp_theme = $this->wpTheme->getTheme();

        // Use our specialized container interface
        $this->app->bindShared('wp.theme', fn () => $this->wp_theme);
    }

    /**
     * Register theme providers
     */
    private function registerThemeProvider(): void
    {
        $providers = (array) ThemeConfig::get('theme.providers', []);

        foreach ($providers as $provider) {
            // Using our specialized container interface
            $this->app->registerProvider($provider);
        }
    }

    /**
     * Set up themes
     */
    public function setThemes(): void
    {
        // Use the interface instead of direct function call
        $childTheme = $this->wpTheme->getStylesheet();

        $this->getThemeService()->load($childTheme);

        $themeConfigs = [
            'supports',
            'menus',
            'templates',
            'sidebars',
            'images',
            'gutenberg',
            'providers',
        ];

        // Using our specialized container interface
        if (! $this->app->isConfigurationCached()) {
            foreach ($themeConfigs as $themeConfig) {
                $this->mergeConfigFrom($this->getThemeService()->path("config/{$themeConfig}.php"), "theme.{$themeConfig}");
            }
        }
    }

    /**
     * Check if the theme is identical to the given theme
     */
    public function isThemeIdentical($childTheme): bool
    {
        // Use the interface instead of direct function call
        return $this->wpTheme->getTemplate() === $childTheme;
    }

    /**
     * Merge configuration from a file
     */
    protected function mergeConfigFrom($path, $key): void
    {
        $config = $this->app->getConfig($key, []);
        if (! file_exists($path)) {
            return;
        }
        $this->app->setConfig($key, array_merge(require $path, $config));
    }

    /**
     * Override the theme URI
     */
    protected function overrideThemeUri(): void
    {
        $this->filter->add('theme_file_uri', function ($path): string {
            $relativePath = $this->getRelativePath($path);

            return (string) (new AssetFile($relativePath))->from('theme');
        });
    }

    /**
     * Get the relative path
     */
    protected function getRelativePath(string $fullPath): string
    {
        // Use the interface instead of direct function call
        $stylesheetUri = $this->wpTheme->getStylesheetDirectoryUri();

        return str_replace($stylesheetUri, '', $fullPath);
    }
}
