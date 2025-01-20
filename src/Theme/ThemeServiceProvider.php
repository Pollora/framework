<?php

declare(strict_types=1);

namespace Pollora\Theme;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Theme;
use Pollora\Theme\Commands\MakeThemeCommand;
use Pollora\Theme\Commands\RemoveThemeCommand;
use Pollora\Theme\Factories\ComponentFactory;
use Pollora\Foundation\Support\IncludesFiles;

/**
 * Provide extra blade directives to aid in WordPress view development.
 */
class ThemeServiceProvider extends ServiceProvider
{
    use IncludesFiles;
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
        $this->app->singleton('theme', fn (Container $app): \Pollora\Theme\ThemeManager => new ThemeManager(
            $app,
            $app['view']->getFinder(),
            $app['translator']->getLoader()
        ));

        $this->app->singleton('theme.generator', fn (Container $app): \Pollora\Theme\Commands\MakeThemeCommand => new MakeThemeCommand($app['config'], $app['files']));

        $this->app->singleton('theme.remover', fn (Container $app): \Pollora\Theme\Commands\RemoveThemeCommand => new RemoveThemeCommand($app['config'], $app['files']));

        $this->commands([
            'theme.generator',
            'theme.remover',
        ]);

        $this->app->singleton(ComponentFactory::class, fn ($app): \Pollora\Theme\Factories\ComponentFactory => new ComponentFactory($app));

        $this->app->singleton(ThemeComponentProvider::class, fn ($app): \Pollora\Theme\ThemeComponentProvider => new ThemeComponentProvider($app, $app->make(ComponentFactory::class)));

        $this->app->make(ThemeComponentProvider::class)->register();

        Action::add('after_setup_theme', [$this, 'bootTheme']);
    }

    /**
     * Perform post-registration booting of services.
     */
    public function bootTheme(): void
    {
        $theme = Theme::instance();

        if (!$theme->theme()) {
            return;
        }
        
        $themeInclude = $theme->theme()->getThemeIncDir();

        if (File::exists($themeInclude) && File::isDirectory($themeInclude)) {
            $theme->includes([$theme->theme()->getThemeIncDir()]);
        }

        $currentTheme = $theme->active();

        $this->app['asset.container']->addContainer('theme', [
            'hot_file' => public_path("{$currentTheme}.hot"),
            'build_directory' => "build/{$currentTheme}",
            'manifest_path' => 'manifest.json',
            'base_path' => '',
        ]);

        $this->app['asset.container']->setDefaultContainer('theme');

        $this->loadConfigurations();

        $this->app->make(ThemeComponentProvider::class)->boot();

        $this->directives()
            ->each(function ($directive, $function): void {
                Blade::directive($function, $directive);
            });
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
