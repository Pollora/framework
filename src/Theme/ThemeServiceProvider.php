<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Pollen\Gutenberg\Pattern;

/**
 * Provide extra blade directives to aid in WordPress view development.
 */
class ThemeServiceProvider extends ServiceProvider
{
    protected $wp_theme;

    protected $theme_root;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->directives()
            ->each(function ($directive, $function) {
                Blade::directive($function, $directive);
            });
    }

    /**
     * Get the Blade directives.
     *
     * @return array
     */
    public function directives()
    {
        return collect(['Directives'])
            ->flatMap(function ($directive) {
                if (file_exists($directives = __DIR__.'/'.$directive.'.php')) {
                    return require_once $directives;
                }
            });
    }

    /**
     * Registers the theme.
     *
     * This method initializes various components of the theme such as
     * the theme initializer, menus, support options, sidebar, pattern,
     * templates, and image size settings.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('theme', function ($app) {
            return new Theme(); // Remplacez ceci par la manière dont vous instanciez votre classe Theme
        });
        (new ThemeInitializer($this))->init();
        (new Pattern($this))->init();
        (new Menus($this))->init();
        (new Support($this))->init();
        (new Sidebar($this))->init();
        (new Templates($this))->init();
        (new ImageSize($this))->init();
    }

    /**
     * Register a service provider.
     *
     * @param  string  $provider The class or interface name of the service provider.
     * @return void
     */
    public function registerProvider($provider)
    {
        $this->app->register($provider);
    }

    /**
     * Bind a singleton instance to the container.
     *
     * @return void
     */
    public function singleton($abstract, $concrete)
    {
        $this->app->singleton($abstract, $concrete);
    }

    /**
     * Registers a theme configuration file.
     *
     * This method reads and merges the configuration settings from a theme
     * configuration file into the application's configuration.
     *
     * @param  string  $path The path to the theme configuration file.
     * @param  string  $key The configuration key to use for the merged settings.
     * @return void
     */
    public function registerThemeConfig($path, $key)
    {
        $this->mergeConfigFrom($path, $key);
    }
}
