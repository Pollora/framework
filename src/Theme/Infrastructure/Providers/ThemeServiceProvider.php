<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Infrastructure\Providers\CollectionServiceProvider;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Config\Infrastructure\Providers\ConfigServiceProvider;
use Pollora\Modules\Infrastructure\Providers\ModuleServiceProvider;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Application\Services\ThemeRegistrar;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Domain\Models\LaravelThemeModule;
use Pollora\Theme\Domain\Support\ThemeCollection;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Pollora\Theme\Infrastructure\Repositories\ThemeRepository;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;
use Pollora\Theme\Infrastructure\Services\WordPressThemeAdapter;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;
use Pollora\Theme\UI\Console\Commands\ThemeStatusCommand;
use Pollora\Theme\UI\Console\MakeThemeCommand;
use Pollora\Theme\UI\Console\RemoveThemeCommand;

/**
 * Simplified Theme Service Provider with clear responsibilities.
 */
class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register theme services.
     */
    public function register(): void
    {
        // Register core dependencies first
        $this->registerCoreDependencies();

        // Initialize utility classes immediately after dependencies are registered
        $this->initializeUtilityClasses();

        // Register theme-specific services
        $this->registerThemeServices();

        // Register console commands
        $this->registerCommands();
    }

    /**
     * Boot theme services.
     */
    public function boot(): void
    {
        // Register theme directories for WordPress
        $this->registerThemeDirectories();

        // Setup theme boot process (simplified)
        $this->setupThemeBoot();
    }

    /**
     * Register core dependencies.
     */
    protected function registerCoreDependencies(): void
    {
        $this->app->register(ConfigServiceProvider::class);
        $this->app->register(CollectionServiceProvider::class);
        $this->app->register(ModuleServiceProvider::class);

        // Load theme configuration early
        $this->loadThemeConfiguration();
    }

    /**
     * Initialize utility classes with their dependencies.
     * This must be called early in the register phase.
     */
    protected function initializeUtilityClasses(): void
    {
        // Initialize ThemeConfig immediately with the config repository
        if ($this->app->bound(ConfigRepositoryInterface::class)) {
            $config = $this->app->make(ConfigRepositoryInterface::class);
            ThemeConfig::setRepository($config);
        } else {
            // If not bound yet, use a callback for when it becomes available
            $this->app->afterResolving(ConfigRepositoryInterface::class, function (ConfigRepositoryInterface $config): void {
                ThemeConfig::setRepository($config);
            });
        }

        // Initialize ThemeCollection
        if ($this->app->bound(CollectionFactoryInterface::class)) {
            $factory = $this->app->make(CollectionFactoryInterface::class);
            ThemeCollection::setFactory($factory);
        } else {
            $this->app->afterResolving(CollectionFactoryInterface::class, function (CollectionFactoryInterface $factory): void {
                ThemeCollection::setFactory($factory);
            });
        }
    }

    /**
     * Register theme-specific services.
     */
    protected function registerThemeServices(): void
    {
        // Register domain container interface
        $this->app->singleton(ContainerInterface::class, fn ($app): \Pollora\Theme\Domain\Contracts\ContainerInterface => $this->createDomainContainer($app));

        // WordPress theme interface
        $this->app->singleton(WordPressThemeInterface::class, WordPressThemeAdapter::class);

        // Theme parser
        $this->app->singleton(WordPressThemeParser::class);

        // Theme registrar for self-registration
        $this->app->singleton(ThemeRegistrarInterface::class, fn ($app): \Pollora\Theme\Application\Services\ThemeRegistrar => new ThemeRegistrar(
            $app->make(ContainerInterface::class),
            $app->make(WordPressThemeParser::class)
        ));

        // Theme repository (deprecated - themes now use self-registration)
        // Kept for backward compatibility only
        $this->app->singleton('theme.repository', fn ($app): \Pollora\Theme\Infrastructure\Repositories\ThemeRepository => new ThemeRepository(
            $app,
            $app->make(WordPressThemeParser::class),
            $app->make(CollectionFactoryInterface::class)
        ));

        // Theme autoloader
        $this->app->singleton(ThemeAutoloader::class);

        // Theme service
        $this->app->singleton(ThemeService::class, fn ($app): \Pollora\Theme\Application\Services\ThemeManager => new ThemeManager(
            $app,
            $app->get('view')->getFinder(),
            $app->make('translator')->getLoader(),
            $app->bound('theme.repository') ? $app->make('theme.repository') : null,
            $app->make(ThemeRegistrarInterface::class)
        ));

        // Backward compatibility alias
        $this->app->singleton('theme', fn ($app) => $app->make(ThemeService::class));

        // Class alias for backward compatibility
        if (! class_exists('Pollora\\Modules\\Domain\\Models\\LaravelThemeModule')) {
            class_alias(LaravelThemeModule::class, 'Pollora\\Modules\\Domain\\Models\\LaravelThemeModule');
        }
    }

    /**
     * Register theme directories for WordPress discovery.
     */
    protected function registerThemeDirectories(): void
    {
        try {
            $baseThemePath = ThemeConfig::get('path', base_path('themes'));
        } catch (\RuntimeException) {
            // Fallback if ThemeConfig is not initialized yet
            $baseThemePath = base_path('themes');
        }

        if ($baseThemePath && is_dir($baseThemePath)) {
            if (! isset($GLOBALS['wp_theme_directories'])) {
                $GLOBALS['wp_theme_directories'] = [];
            }

            if (! in_array($baseThemePath, $GLOBALS['wp_theme_directories'], true)) {
                $GLOBALS['wp_theme_directories'][] = $baseThemePath;
            }
        }
    }

    /**
     * Setup theme boot process.
     */
    protected function setupThemeBoot(): void
    {
        // Setup Blade directive for theme checking
        if (class_exists(\Illuminate\Support\Facades\Blade::class)) {
            Blade::if('theme', function (string $name) {
                /** @var ThemeService $themeManager */
                $themeManager = app(ThemeService::class);

                return $themeManager->hasTheme($name);
            });
        }
    }

    /**
     * Load theme configuration.
     */
    protected function loadThemeConfiguration(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/theme.php', 'theme');
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        $this->app->singleton('theme.generator', fn ($app): \Pollora\Theme\UI\Console\MakeThemeCommand => new MakeThemeCommand($app->make('config'), $app->make('files')));

        $this->app->singleton('theme.remover', fn ($app): \Pollora\Theme\UI\Console\RemoveThemeCommand => new RemoveThemeCommand($app->make('config'), $app->make('files')));

        $this->app->singleton('theme.status', fn ($app): \Pollora\Theme\UI\Console\Commands\ThemeStatusCommand => new ThemeStatusCommand);

        $this->commands([
            'theme.generator',
            'theme.remover',
            'theme.status',
        ]);
    }

    /**
     * Create domain container adapter.
     */
    protected function createDomainContainer($app): ContainerInterface
    {
        return new class($app) implements ContainerInterface
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
                return false;
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
}
