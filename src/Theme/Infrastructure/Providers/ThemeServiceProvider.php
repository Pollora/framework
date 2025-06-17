<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Infrastructure\Providers\CollectionServiceProvider;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Config\Infrastructure\Providers\ConfigServiceProvider;
use Pollora\Foundation\Support\IncludesFiles;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Providers\ModuleServiceProvider;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Domain\Contracts\ThemeDiscoveryInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Domain\Models\LaravelThemeModule;
use Pollora\Theme\Domain\Support\ThemeCollection;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Pollora\Theme\Infrastructure\Adapters\WordPressThemeDiscovery;
use Pollora\Theme\Infrastructure\Repositories\ThemeRepository;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;
use Pollora\Theme\Infrastructure\Services\WordPressThemeAdapter;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;
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
        // Register Config and Collection providers
        $this->app->register(ConfigServiceProvider::class);
        $this->app->register(CollectionServiceProvider::class);

        // Register Modular Theme System
        $this->app->register(ModuleServiceProvider::class);

        // Initialize utility classes
        $this->initializeUtilityClasses();

        // Register WordPress theme interface binding
        $this->app->singleton(
            WordPressThemeInterface::class,
            WordPressThemeAdapter::class
        );

        // Register modular theme system services
        $this->registerModularThemeServices();

        // Register ThemeService interface binding
        $this->app->singleton(ThemeService::class, function ($app) {
            return new ThemeManager(
                $app,
                $app->get('view')->getFinder(),
                $app->make('translator')->getLoader(),
                $app->make(ModuleRepositoryInterface::class),
                $app->make(ThemeDiscoveryInterface::class)
            );
        });

        // Also register theme alias for backward compatibility
        $this->app->singleton('theme', function ($app) {
            return $app->make(ThemeService::class);
        });

        // Register remaining services
        $this->registerCommands();
    }

    public function boot()
    {
        $this->registerComponentServices();

        // Boot modular theme system
        $this->bootModularThemeSystem();
    }

    /**
     * Initialize the utility classes with their respective dependencies.
     */
    protected function initializeUtilityClasses(): void
    {
        // Initialize ThemeConfig
        $this->app->afterResolving(ConfigRepositoryInterface::class, function (ConfigRepositoryInterface $config) {
            ThemeConfig::setRepository($config);
        });

        // Initialize ThemeCollection
        $this->app->afterResolving(CollectionFactoryInterface::class, function (CollectionFactoryInterface $factory) {
            ThemeCollection::setFactory($factory);
        });
    }

    /**
     * Load helper functions.
     */
    protected function loadHelpers(): void
    {
        // No longer needed as we're using utility classes
    }

    /**
     * Register modular theme system services.
     */
    protected function registerModularThemeServices(): void
    {
        // Register theme parser
        $this->app->singleton(WordPressThemeParser::class);

        // Register theme discovery service
        $this->app->singleton(ThemeDiscoveryInterface::class, function ($app) {
            return new WordPressThemeDiscovery(
                $app->make(CollectionFactoryInterface::class),
                $app->make(WordPressThemeParser::class)
            );
        });

        // Register theme repository as module repository
        $this->app->singleton(ModuleRepositoryInterface::class, function ($app) {
            return new ThemeRepository(
                $app,
                $app->make(ThemeDiscoveryInterface::class),
                $app->make(WordPressThemeParser::class),
                $app->make(CollectionFactoryInterface::class)
            );
        });

        // Register ThemeAutoloader service
        $this->app->singleton(ThemeAutoloader::class, function ($app) {
            return new ThemeAutoloader($app);
        });

        // Register class alias for LaravelThemeModule backward compatibility
        if (! class_exists('Pollora\\Modules\\Domain\\Models\\LaravelThemeModule')) {
            class_alias(LaravelThemeModule::class, 'Pollora\\Modules\\Domain\\Models\\LaravelThemeModule');
        }
    }

    /**
     * Boot modular theme system.
     */
    protected function bootModularThemeSystem(): void
    {
        // The ModuleServiceProvider now handles registration and booting of modules
        // We just need to create the @theme() blade directive for backward compatibility
        if (class_exists('Illuminate\\Support\\Facades\\Blade')) {
            \Illuminate\Support\Facades\Blade::if('theme', function (string $name) {
                /** @var ThemeService $themeManager */
                $themeManager = app(ThemeService::class);

                return $themeManager->hasTheme($name);
            });
        }
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
     * Register component provider
     */
    protected function registerComponentServices(): void
    {
        // Register our domain container interface to Laravel's application
        $this->app->singleton(
            \Pollora\Theme\Domain\Contracts\ContainerInterface::class,
            function ($app) {
                // Return a very simple container implementation that delegates to Laravel
                return new class($app) implements \Pollora\Theme\Domain\Contracts\ContainerInterface
                {
                    public function __construct(protected $app) {}

                    public function get(string $id): mixed
                    {
                        return $this->app->make($id);
                    }

                    public function has(string $id): bool
                    {
                        return $this->app->bound($id);
                    }

                    public function registerProvider(string|object $provider): void
                    {
                        $this->app->register($provider);
                    }

                    public function bindShared(string $abstract, mixed $concrete): void
                    {
                        $this->app->singleton($abstract, $concrete);
                    }

                    public function isConfigurationCached(): bool
                    {
                        return false; // Always assume configs need to be loaded
                    }

                    public function getConfig(string $key, mixed $default = null): mixed
                    {
                        return $this->app['config']->get($key, $default);
                    }

                    public function setConfig(string $key, mixed $value): void
                    {
                        $this->app['config']->set($key, $value);
                    }
                };
            }
        );

        $this->app->singleton(
            \Pollora\Theme\Infrastructure\Services\ComponentFactory::class,
            function ($app) {
                return new \Pollora\Theme\Infrastructure\Services\ComponentFactory($app);
            }
        );

        $this->app->singleton(ThemeComponentProvider::class, function ($app) {
            return new ThemeComponentProvider(
                $app
            );
        });

        // Register component provider
        $this->app->make(ThemeComponentProvider::class)->register();

        // Register theme setup action
        /** @var Action $action */
        $action = $this->app->make(Action::class);

        if ($action !== null) {
            $action->add('after_setup_theme', [$this, 'bootTheme']);
        }
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
            // Load all PHP files in the theme's include directory
            $this->loadFilesFrom($themeInclude);
        }

        $currentTheme = $themeService->active();

        $this->app->make(AssetManager::class)->addContainer('theme', [
            'hot_file' => public_path("{$currentTheme}.hot"),
            'build_directory' => "build/{$currentTheme}",
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
        ]);

        // TODO: If needed, implement setDefaultContainer logic in AssetManager

        $this->loadConfigurations();

        $this->directives()
            ->each(function ($directive, $function): void {
                Blade::directive($function, $directive);
            });
    }

    /**
     * Load all PHP files from a directory recursively.
     *
     * @param  string  $directory  The directory to load files from
     */
    protected function loadFilesFrom(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }

        foreach (File::directories($directory) as $subDir) {
            $this->loadFilesFrom($subDir);
        }
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
        $directiveCollection = ThemeCollection::make(['Directives']);

        if ($directiveCollection instanceof Collection) {
            return $directiveCollection->flatMap(function ($directive) {
                if (file_exists($directives = __DIR__.'/'.$directive.'.php')) {
                    return require $directives;
                }
            });
        }

        // Fallback if the returned instance is not a Laravel Collection
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
