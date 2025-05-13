<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Asset\Domain\Models\AssetFile;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Support\ThemeConfig;

class ThemeInitializer implements ThemeComponent
{
    protected $themeRoot;

    protected $wp_theme;

    protected Application $app;

    protected Action $action;

    protected Filter $filter;

    protected ?ThemeService $themeService = null;

    protected ServiceLocator $locator;

    protected $themeManager;
    
    protected ConfigRepositoryInterface $config;

    /**
     * Create a new theme initializer
     */
    public function __construct(ServiceLocator $locator, ConfigRepositoryInterface $config)
    {
        $this->locator = $locator;
        $this->app = $locator->resolve(Application::class);
        $this->config = $config;
        $this->themeRoot = ThemeConfig::get('theme.base_path');
        $this->action = $locator->resolve(Action::class);
        $this->filter = $locator->resolve(Filter::class);

        $this->filter->add('stylesheet_directory', $this->overrideStylesheetDirectory(...), 90, 3);
    }

    /**
     * Get the ThemeService, resolving it if not already done
     */
    protected function getThemeService(): ThemeService
    {
        if ($this->themeService === null) {
            $this->themeService = $this->locator->resolve(ThemeService::class);

            // Fallback to 'theme' binding if ThemeService interface isn't registered yet
            if ($this->themeService === null) {
                $this->themeService = $this->locator->resolve('theme');
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
            // WordPress specific function - we'll keep this in the implementation
            // but it would be better to inject a WordPress service abstraction
            if (function_exists('wp_installing') && \wp_installing()) {
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
        // WordPress specific function - would need proper abstraction
        if (function_exists('register_theme_directory')) {
            \register_theme_directory($this->themeRoot);
        }

        $this->setThemes();
        $this->registerThemeProvider();

        // WordPress globals - would need proper abstraction
        if (defined('WP_CONTENT_DIR')) {
            $GLOBALS['wp_theme_directories'][] = WP_CONTENT_DIR.'/themes';
        }

        // WordPress function - would need proper abstraction
        if (function_exists('wp_get_theme')) {
            $this->wp_theme = \wp_get_theme();
            $this->app->singleton('wp.theme', fn () => $this->wp_theme);
        }
    }

    /**
     * Register theme providers
     */
    private function registerThemeProvider(): void
    {
        foreach ((array) ThemeConfig::get('theme.providers', []) as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Set up themes
     */
    public function setThemes(): void
    {
        // WordPress function - would need proper abstraction
        $childTheme = function_exists('get_stylesheet') ? get_stylesheet() : 'default';

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

        // Laravel method - better to check if method exists
        if (method_exists($this->app, 'configurationIsCached') && ! $this->app->configurationIsCached()) {
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
        // WordPress function - would need proper abstraction
        return function_exists('get_template') && get_template() === $childTheme;
    }

    /**
     * Merge configuration from a file
     */
    protected function mergeConfigFrom($path, $key): void
    {
        $config = $this->app['config']->get($key, []);
        if (! file_exists($path)) {
            return;
        }
        $this->app['config']->set($key, array_merge(require $path, $config));
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
        // WordPress function - would need proper abstraction
        $stylesheetUri = function_exists('get_stylesheet_directory_uri')
            ? get_stylesheet_directory_uri().'/'
            : '/';

        return str_replace($stylesheetUri, '', $fullPath);
    }
}
