<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Pollen\Gutenberg\Pattern;
use Pollen\Support\Facades\Filter;
use Pollen\Theme\Commands\MakeThemeCommand;
use Pollen\Theme\Commands\RemoveThemeCommand;
use Pollen\Theme\Factories\ComponentFactory;

/**
 * Provide extra blade directives to aid in WordPress view development.
 */
class ThemeServiceProvider extends ServiceProvider
{
    protected $wp_theme;

    protected $theme_root;

    /**
     * Registers the theme.
     *
     * This method initializes various components of the theme such as
     * the theme initializer, menus, support options, sidebar, pattern,
     * templates, and image size settings.
     */
    public function register(): void
    {
        $this->app->singleton('theme', fn(Container $app): \Pollen\Theme\ThemeManager => new ThemeManager(
            $app,
            $app['view']->getFinder(),
            $app['translator']->getLoader()
        ));

        $this->app->singleton('theme.generator', fn(Container $app): \Pollen\Theme\Commands\MakeThemeCommand => new MakeThemeCommand($app['config'], $app['files']));

        $this->app->singleton('theme.remover', fn(Container $app): \Pollen\Theme\Commands\RemoveThemeCommand => new RemoveThemeCommand($app['config'], $app['files']));

        $this->commands([
            'theme.generator',
            'theme.remover',
        ]);

        $this->app->singleton(ComponentFactory::class, fn($app): \Pollen\Theme\Factories\ComponentFactory => new ComponentFactory($app));

        $this->app->singleton(ThemeComponentProvider::class, fn($app): \Pollen\Theme\ThemeComponentProvider => new ThemeComponentProvider($app, $app->make(ComponentFactory::class)));

        $this->app->make(ThemeComponentProvider::class)->register();

        $this->overrideThemeUri();
    }

    protected function overrideThemeUri(): void
    {
        Filter::add('theme_file_uri', function($uri): string {
            $relativePath = str_replace(get_stylesheet_directory_uri() . '/', '', $uri);
            $container = app('asset.container')->get('theme');
            return $this->findThemeAsset($relativePath);
        });
    }

    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $this->publishConfigurations();
        $this->loadConfigurations();
        $this->imageMacro();

        $this->app->make(ThemeComponentProvider::class)->boot();

        $this->directives()
            ->each(function ($directive, $function): void {
                Blade::directive($function, $directive);
            });
    }


    protected function imageMacro(): void
    {
        $provider = $this;
        Vite::macro('image', fn (string $asset) => $provider->findThemeAsset($asset, 'images'));
        Vite::macro('font', fn (string $asset) => $provider->findThemeAsset($asset, 'fonts'));
        Vite::macro('css', fn (string $asset) => $provider->findThemeAsset($asset, 'css'));
        Vite::macro('js', fn (string $asset) => $provider->findThemeAsset($asset, 'js'));
    }

    public function findThemeAsset(string $path, string $prefix = ''):string {
        $container = app('asset.container')->get('theme');
        $path = str_replace(get_stylesheet_directory_uri() . '/', '', $path);
        $prefix = $prefix? 'assets/'.$prefix.'/' : '';
        $path = $prefix.$path;
        return Vite::useHotFile($container->getHotFile())->useBuildDirectory($container->getBuildDirectory())->asset($path);
    }

    protected function publishConfigurations(): void
    {
        $this->publishes([
            __DIR__.'/config/theme.php' => config_path('theme.php'),
        ], 'pollen-theme-config');
    }

    protected function loadConfigurations(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/theme.php', 'theme');
    }

    /**
     * Get the Blade directives.
     */
    public function directives(): Collection
    {
        return collect(['Directives'])
            ->flatMap(function ($directive) {
                if (file_exists($directives = __DIR__.'/'.$directive.'.php')) {
                    return require_once $directives;
                }
            });
    }

    /**
     * Register a service provider.
     *
     * @param  string  $provider  The class or interface name of the service provider.
     */
    public function registerProvider($provider): void
    {
        $this->app->register($provider);
    }

    /**
     * Bind a singleton instance to the container.
     */
    public function singleton($abstract, $concrete): void
    {
        $this->app->singleton($abstract, $concrete);
    }

    /**
     * Registers a theme configuration file.
     *
     * This method reads and merges the configuration settings from a theme
     * configuration file into the application's configuration.
     *
     * @param  string  $path  The path to the theme configuration file.
     * @param  string  $key  The configuration key to use for the merged settings.
     */
    public function registerThemeConfig($path, $key): void
    {
        $this->mergeConfigFrom($path, $key);
    }
}
