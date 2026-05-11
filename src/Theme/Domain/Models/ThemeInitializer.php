<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Pollora\Asset\Infrastructure\Services\AssetFile;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contracts\Action;
use Pollora\Hook\Domain\Contracts\Filter;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
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
        } catch (\RuntimeException) {
            // Fallback if ThemeConfig is not initialized yet
            $this->themeRoot = base_path('themes');
        }

        $this->action = $this->app->get(Action::class);
        $this->filter = $this->app->get(Filter::class);
        $this->wpTheme = $this->app->get(WordPressThemeInterface::class);
        $this->registrar = $this->app->get(ThemeRegistrarInterface::class);

        $this->filter->add('pre_option_stylesheet_root', $this->resetThemeRootOption(...));
        $this->filter->add('pre_option_template_root', $this->resetThemeRootOption(...));
    }

    /**
     * Get the ThemeService, resolving it if not already done
     */
    protected function getThemeService(): ThemeService
    {
        if (! $this->themeService instanceof ThemeService) {
            $this->themeService = $this->app->get(ThemeService::class);

            // Fallback to 'theme' binding if ThemeService interface isn't registered yet
            if (! $this->themeService instanceof ThemeService) {
                $this->themeService = $this->app->get('theme');
            }

            if (! $this->themeService instanceof ThemeService) {
                throw new \RuntimeException('Unable to resolve ThemeService. Make sure it is properly registered.');
            }
        }

        return $this->themeService;
    }

    /**
     * Register the theme initializer.
     *
     * Hooks into 'after_setup_theme' at priority 1 to initialize the theme
     * early in the WordPress lifecycle, and overrides the theme URI for
     * proper asset resolution.
     */
    public function register(): void
    {
        $this->action->add('after_setup_theme', $this->initializeTheme(...), 1);
        $this->overrideThemeUri();
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

        if ($activeTheme instanceof ThemeModuleInterface) {
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
        $this->app->bindShared('wp.theme', fn (): object => $this->wp_theme);
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
        if (in_array($themeName, [null, '', '0'], true)) {
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
    protected function mergeConfigFrom($path, string $key): void
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
        $this->filter->add('theme_file_uri', function (string $path): string {
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
