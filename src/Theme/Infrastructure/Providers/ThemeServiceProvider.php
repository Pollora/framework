<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Container\Infrastructure\ContainerServiceLocator;
use Pollora\Foundation\Support\IncludesFiles;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Support\Facades\Theme;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Services\TemplateHierarchy;
use Pollora\Theme\Infrastructure\Services\ComponentFactory;
use Pollora\Theme\UI\Console\MakeThemeCommand;
use Pollora\Theme\UI\Console\RemoveThemeCommand;

/**
 * Provide extra blade directives to aid in WordPress view development.
 */
class ThemeServiceProvider extends ServiceProvider
{
    use IncludesFiles;

    /**
     * Registers the theme.
     *
     * This method initializes various components of the theme such as
     * the theme initializer, menus, support options, sidebar, pattern,
     * templates, and image size settings.
     */
    public function register(): void
    {
        // Register ServiceLocator first as many other services depend on it
        $this->app->singleton(ServiceLocator::class, function ($app) {
            return new ContainerServiceLocator($app);
        });

        // Register ThemeService interface binding
        $this->app->singleton(ThemeService::class, function ($app) {
            return new ThemeManager(
                $app,
                $app->make('view')->getFinder(),
                $app->make('translator')->getLoader()
            );
        });

        // Also register theme alias for backward compatibility
        $this->app->singleton('theme', function ($app) {
            return $app->make(ThemeService::class);
        });

        // Register remaining services
        $this->registerCommands();
        $this->registerComponentServices();
        $this->registerTemplateHierarchy();
    }

    /**
     * Register the console commands
     */
    protected function registerCommands(): void
    {
        $this->app->singleton('theme.generator', function ($app) {
            return new MakeThemeCommand($app->make('config'), $app->make('files'));
        });

        $this->app->singleton('theme.remover', function ($app) {
            return new RemoveThemeCommand($app->make('config'), $app->make('files'));
        });

        $this->commands([
            'theme.generator',
            'theme.remover',
        ]);
    }

    /**
     * Register component factory and provider
     */
    protected function registerComponentServices(): void
    {
        $this->app->singleton(ComponentFactory::class, function ($app) {
            return new ComponentFactory($app->make(ServiceLocator::class));
        });

        $this->app->singleton(ThemeComponentProvider::class, function ($app) {
            return new ThemeComponentProvider(
                $app->make(ServiceLocator::class),
                $app->make(ComponentFactory::class)
            );
        });

        // Register component provider
        $this->app->make(ThemeComponentProvider::class)->register();

        // Register theme setup action
        /** @var ServiceLocator $locator */
        $locator = $this->app->make(ServiceLocator::class);
        /** @var Action $action */
        $action = $locator->resolve(Action::class);

        if ($action !== null) {
            $action->add('after_setup_theme', [$this, 'bootTheme']);
        }
    }

    /**
     * Register template hierarchy service
     */
    protected function registerTemplateHierarchy(): void
    {
        $this->app->singleton(TemplateHierarchy::class, function ($app) {
            $locator = $app->make(ServiceLocator::class);

            return new TemplateHierarchy(
                $app->make('config'),
                $locator->resolve(Action::class),
                $locator->resolve(Filter::class)
            );
        });
    }

    /**
     * Perform post-registration booting of services.
     */
    public function bootTheme(): void
    {
        /** @var ThemeService $themeService */
        $themeService = $this->app->make(ThemeService::class);

        if (! $themeService->theme()) {
            return;
        }

        $themeInclude = $themeService->theme()->getThemeIncDir();

        if (File::exists($themeInclude) && File::isDirectory($themeInclude)) {
            // If the class uses IncludesFiles trait
            if (method_exists($themeService, 'includes')) {
                $themeService->includes([$themeService->theme()->getThemeIncDir()]);
            }
        }

        $currentTheme = $themeService->active();

        $this->app->make(AssetManager::class)->addContainer('theme', [
            'hot_file' => public_path("{$currentTheme}.hot"),
            'build_directory' => "build/{$currentTheme}",
            'manifest_path' => 'manifest.json',
            'base_path' => '',
        ]);

        // TODO: If needed, implement setDefaultContainer logic in AssetManager

        $this->loadConfigurations();

        $this->directives()
            ->each(function ($directive, $function): void {
                Blade::directive($function, $directive);
            });
    }

    protected function loadConfigurations(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/theme.php', 'theme');
    }

    /**
     * Get the Blade directives.
     */
    public function directives(): Collection
    {
        return collect(['Directives'])
            ->flatMap(function ($directive) {
                if (file_exists($directives = __DIR__.'/'.$directive.'.php')) {
                    return require $directives;
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
    public function registerThemeConfig(string $path, string $key): void
    {
        $this->mergeConfigFrom($path, $key);
    }
}
