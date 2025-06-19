<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Pollora\Asset\Infrastructure\Services\AssetFile;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Domain\Support\ThemeConfig;

/**
 * Theme initializer for self-registered themes.
 *
 * This version works with themes that register themselves via functions.php
 * instead of relying on automatic discovery and database queries.
 */
class ThemeInitializer implements ThemeComponent
{
    protected $themeRoot;

    protected $wp_theme;

    protected Action $action;

    protected Filter $filter;

    protected ?ThemeService $themeService = null;

    protected WordPressThemeInterface $wpTheme;

    protected ThemeRegistrarInterface $registrar;

    /**
     * Create a new self-registered theme initializer
     */
    public function __construct(
        protected ContainerInterface $app,
        protected ConfigRepositoryInterface $config
    ) {
        // Get theme root safely - use fallback if ThemeConfig is not initialized yet
        try {
            $this->themeRoot = ThemeConfig::get('path', base_path('themes'));
        } catch (\RuntimeException $e) {
            // Fallback if ThemeConfig is not initialized yet
            $this->themeRoot = base_path('themes');
        }

        $this->action = $this->app->get(Action::class);
        $this->filter = $this->app->get(Filter::class);
        $this->wpTheme = $this->app->get(WordPressThemeInterface::class);
        $this->registrar = $this->app->get(ThemeRegistrarInterface::class);

        // $this->filter->add('template_directory', $this->overrideThemeDirectory(...), 90, 3);
        // $this->filter->add('stylesheet_directory', $this->overrideThemeDirectory(...), 90, 3);
        // Handle custom theme roots.
        $this->filter->add('pre_option_stylesheet_root', $this->resetThemeRootOption(...));
        $this->filter->add('pre_option_template_root', $this->resetThemeRootOption(...));
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
     * Register the theme initializer
     */
    public function register(): void
    {
        // @TODO clean
        $this->action->add('after_setup_theme', function (): void {
            $this->initializeTheme();
        }, 1);

        $this->overrideThemeUri();
    }

    /**
     * Override the stylesheet directory URI
     * @TODO : clean
     */
    public function overrideThemeDirectory(string $stylesheetDirUri, string $stylesheet, string $themeRootUri): string
    {
        // Get the active theme from the registrar
        $activeTheme = $this->registrar->getActiveTheme();

        if ($activeTheme) {
            return str_replace($themeRootUri, $activeTheme->getPath(), $stylesheetDirUri);
        }

        // No fallback - theme must be self-registered
        return $stylesheetDirUri;
    }

    /**
     * Force template and stylesheet root to be false when called from the database
     */
    protected function resetThemeRootOption(string|bool $path): bool
    {
        return false;
    }

    /**
     * Initialize the theme
     */
    private function initializeTheme(): void
    {
        // Get the active theme from the registrar
        $activeTheme = $this->registrar->getActiveTheme();

        if ($activeTheme) {
            // Use the registered theme's path
            $this->themeRoot = $activeTheme->getPath();

            // Register theme directory with WordPress
            $this->wpTheme->registerThemeDirectory(dirname($this->themeRoot));

            // Set up theme metadata
            $this->setThemes($activeTheme->getName());
        } else {
            // No theme registered - this is expected behavior
            // Themes must register themselves via functions.php
            return;
        }

        $this->registerThemeProvider();

        // Theme directories are now registered centrally in ThemeServiceProvider
        // No need to manage $GLOBALS['wp_theme_directories'] here

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
        $providers = (array) ThemeConfig::get('providers', []);

        foreach ($providers as $provider) {
            // Using our specialized container interface
            $this->app->registerProvider($provider);
        }
    }

    /**
     * Set up themes
     */
    public function setThemes(?string $themeName = null): void
    {
        // Theme name is required for self-registered themes
        if (! $themeName) {
            throw new \RuntimeException('Theme name is required for self-registered themes.');
        }

        $this->getThemeService()->load($themeName);
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
    public function overrideThemeUri(): void
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
