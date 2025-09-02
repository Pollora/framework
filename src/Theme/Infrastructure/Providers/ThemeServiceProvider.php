<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Infrastructure\Providers\CollectionServiceProvider;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Config\Infrastructure\Providers\ConfigServiceProvider;
use Pollora\Hook\Domain\Contracts\Filter;
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
use Pollora\Theme\Infrastructure\Adapters\DomainContainerAdapter;
use Pollora\Theme\Infrastructure\Repositories\ThemeRepository;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;
use Pollora\Theme\Infrastructure\Services\WordPressThemeAdapter;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;
use Pollora\Theme\UI\Console\Commands\ThemeStatusCommand;
use Pollora\Theme\UI\Console\MakeThemeCommand;
use Pollora\Theme\UI\Console\RemoveThemeCommand;

/**
 * Theme Service Provider with clear separation of concerns.
 *
 * This service provider follows hexagonal architecture principles by:
 * - Registering domain services through interfaces
 * - Using adapters to bridge domain and infrastructure concerns
 * - Maintaining clean separation between layers
 *
 * @author  Pollora Team
 *
 * @since   1.0.0
 */
class ThemeServiceProvider extends ServiceProvider
{
    /**
     * WordPress filter instance for theme-related hooks.
     */
    private Filter $filter;

    /**
     * Register theme services in the container.
     *
     * This method follows a specific order:
     * 1. Core dependencies (Config, Collection, Modules)
     * 2. Utility classes initialization
     * 3. Theme-specific services
     * 4. Console commands
     */
    public function register(): void
    {
        $this->registerCoreDependencies();
        $this->initializeUtilityClasses();
        $this->registerThemeServices();
        $this->registerCommands();

    }

    /**
     * Boot theme services and setup WordPress integration.
     *
     * @param  Filter  $filter  WordPress filter instance for hooks
     */
    public function boot(Filter $filter): void
    {
        $this->filter = $filter;
        $this->registerThemeDirectories();
        $this->setupThemeBoot();

        add_action('after_setup_theme', function () {
            //   dd(get_theme_roots());
        });

    }

    /**
     * Register core dependencies required by the theme system.
     *
     * Registers external service providers and loads theme configuration.
     */
    private function registerCoreDependencies(): void
    {
        $providers = [
            ConfigServiceProvider::class,
            CollectionServiceProvider::class,
            ModuleServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->app->register($provider);
        }

        $this->loadThemeConfiguration();
    }

    /**
     * Initialize utility classes with their dependencies.
     *
     * This must be called early in the register phase to ensure
     * static utility classes have access to their dependencies.
     */
    private function initializeUtilityClasses(): void
    {
        $this->initializeThemeConfig();
        $this->initializeThemeCollection();
    }

    /**
     * Initialize ThemeConfig utility with config repository.
     *
     * Uses immediate binding if available, or deferred resolution callback.
     */
    private function initializeThemeConfig(): void
    {
        if ($this->app->bound(ConfigRepositoryInterface::class)) {
            $config = $this->app->make(ConfigRepositoryInterface::class);
            ThemeConfig::setRepository($config);
        } else {
            $this->app->afterResolving(ConfigRepositoryInterface::class, function (ConfigRepositoryInterface $config): void {
                ThemeConfig::setRepository($config);
            });
        }
    }

    /**
     * Initialize ThemeCollection utility with collection factory.
     *
     * Uses immediate binding if available, or deferred resolution callback.
     */
    private function initializeThemeCollection(): void
    {
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
     * Register all theme-specific services.
     *
     * Organized into logical groups:
     * - Core services (Container, Registrar, Autoloader, Manager)
     * - WordPress-specific services
     * - Backward compatibility aliases
     */
    private function registerThemeServices(): void
    {
        $this->registerCoreServices();
        $this->registerWordPressServices();
        $this->registerBackwardCompatibility();
    }

    /**
     * Register core theme services following hexagonal architecture.
     *
     * All services are registered through their domain interfaces
     * to maintain proper dependency inversion.
     */
    private function registerCoreServices(): void
    {
        // Domain container adapter - bridges domain and Laravel container
        $this->app->singleton(ContainerInterface::class, fn ($app) => $this->createDomainContainer($app));

        // Theme registrar for self-registration pattern
        $this->app->singleton(ThemeRegistrarInterface::class, function ($app) {
            return new ThemeRegistrar(
                $app->make(ContainerInterface::class),
                $app->make(WordPressThemeParser::class)
            );
        });

        // Theme autoloader service
        $this->app->singleton(ThemeAutoloader::class);

        // Main theme service - implements domain interface
        $this->app->singleton(ThemeService::class, function ($app) {
            return new ThemeManager(
                $app,
                $app->get('view')->getFinder(),
                $app->make('translator')->getLoader(),
                $app->bound('theme.repository') ? $app->make('theme.repository') : null,
                $app->make(ThemeRegistrarInterface::class)
            );
        });
    }

    /**
     * Register WordPress-specific services and adapters.
     *
     * These services handle the integration with WordPress theme system.
     */
    private function registerWordPressServices(): void
    {
        // WordPress theme interface adapter
        $this->app->singleton(WordPressThemeInterface::class, WordPressThemeAdapter::class);

        // WordPress theme parser
        $this->app->singleton(WordPressThemeParser::class);

        // Deprecated theme repository - kept for backward compatibility only
        $this->app->singleton('theme.repository', function ($app) {
            return new ThemeRepository(
                $app,
                $app->make(WordPressThemeParser::class),
                $app->make(CollectionFactoryInterface::class)
            );
        });
    }

    /**
     * Register backward compatibility aliases and class mappings.
     *
     * Ensures existing code continues to work after refactoring.
     */
    private function registerBackwardCompatibility(): void
    {
        // Legacy service alias
        $this->app->singleton('theme', fn ($app) => $app->make(ThemeService::class));

        // Legacy class alias for module system
        if (! class_exists('Pollora\\Modules\\Domain\\Models\\LaravelThemeModule')) {
            class_alias(LaravelThemeModule::class, 'Pollora\\Modules\\Domain\\Models\\LaravelThemeModule');
        }
    }

    /**
     * Register theme directories with WordPress for theme discovery.
     *
     * Integrates custom theme paths with WordPress theme system
     * and sets up necessary WordPress hooks.
     */
    private function registerThemeDirectories(): void
    {
        $baseThemePath = $this->getBaseThemePath();

        // Hook into WordPress option system to reset theme root when needed
        $this->filter->add('option_stylesheet_root', $this->resetThemeRootOption(...), PHP_INT_MAX);
        $this->filter->add('site_transient_theme_roots', $this->handleThemeRootsTransient(...), PHP_INT_MAX);

        if ($this->isValidThemeDirectory($baseThemePath)) {
            $this->addToGlobalThemeDirectories($baseThemePath);
        }
    }

    /**
     * Handle the site_transient_theme_roots filter.
     *
     * Normalizes theme roots by fixing invalid paths and updates
     * the transient only when necessary.
     *
     * @param  array|bool  $roots  Theme roots array from WordPress
     * @return array Normalized theme roots
     */
    private function handleThemeRootsTransient(array|bool $roots): array|bool
    {
        if (! $roots) {
            return $roots;
        }

        $updatedRoots = $this->normalizeThemeRoots($roots);

        if ($updatedRoots !== $roots) {
            set_site_transient('theme_roots', $updatedRoots);
        }

        return $updatedRoots;
    }

    /**
     * Normalize theme roots by fixing invalid paths.
     *
     * @param  array  $roots  Theme roots array from WordPress
     * @return array Normalized theme roots
     */
    private function normalizeThemeRoots(array $roots): array
    {
        $normalizedRoots = [];

        foreach ($roots as $themeSlug => $themePath) {
            $normalizedRoots[$themeSlug] = $this->resolveThemePath($themePath);
        }

        return $normalizedRoots;
    }

    /**
     * Resolve theme path to a valid directory.
     *
     * @param  string  $themePath  Original theme path
     * @return string Valid theme directory path
     */
    private function resolveThemePath(string $themePath): string
    {
        // Si le chemin existe déjà, on le garde
        if (file_exists($themePath)) {
            return $themePath;
        }

        // Si c'est un chemin WordPress standard, utiliser le répertoire par défaut
        if ($this->isWordPressThemePath($themePath)) {
            return WP_CONTENT_DIR.'/themes';
        }

        // Sinon, utiliser notre répertoire de base
        return $this->getBaseThemePath();
    }

    /**
     * Check if the theme path is a WordPress standard theme path.
     *
     * @param  string  $themePath  Path to check
     * @return bool True if it's a WordPress theme path
     */
    private function isWordPressThemePath(string $themePath): bool
    {
        return str_contains($themePath, '/content/themes/');
    }

    /**
     * Get the base theme path from configuration.
     *
     * Uses ThemeConfig if available, falls back to default path.
     *
     * @return string The base theme directory path
     */
    private function getBaseThemePath(): string
    {
        return base_path('themes');

    }

    /**
     * Validate if the given path is a valid theme directory.
     *
     * @param  string|null  $path  Path to validate
     * @return bool True if path exists and is a directory
     */
    private function isValidThemeDirectory(?string $path): bool
    {
        return $path && is_dir($path);
    }

    /**
     * Add theme directory to WordPress global theme directories.
     *
     * Ensures the directory is not duplicated in the global array.
     *
     * @param  string  $path  Theme directory path to add
     */
    private function addToGlobalThemeDirectories(string $path): void
    {
        $GLOBALS['wp_theme_directories'] = [$path];
    }

    /**
     * WordPress filter callback to reset theme root option.
     *
     * Forces template and stylesheet root to be false when the path
     * doesn't exist, ensuring WordPress rescans for theme directories.
     *
     * @param  string|bool  $path  Current theme root path from database
     * @return string|bool Original path if exists, false otherwise
     */
    private function resetThemeRootOption(string|bool $path): string|bool
    {
        if (file_exists($path)) {
            return $path;
        }

        // Clear cached WordPress options to force rescan
        delete_option('stylesheet_root');
        delete_option('template_root');

        return false;
    }

    /**
     * Setup theme boot process and integrations.
     *
     * Registers Blade directives and other framework integrations.
     */
    private function setupThemeBoot(): void
    {
        $this->registerBladeDirectives();
    }

    /**
     * Register custom Blade directives for theme functionality.
     *
     * Adds the @theme directive for checking theme existence in templates.
     */
    private function registerBladeDirectives(): void
    {
        if (! class_exists(\Illuminate\Support\Facades\Blade::class)) {
            return;
        }

        Blade::if('theme', function (string $name) {
            /** @var ThemeService $themeManager */
            $themeManager = app(ThemeService::class);

            return $themeManager->hasTheme($name);
        });
    }

    /**
     * Load theme configuration from config file.
     *
     * Merges the theme configuration into the application config.
     */
    private function loadThemeConfiguration(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/theme.php', 'theme');
    }

    /**
     * Register console commands for theme management.
     *
     * Registers artisan commands for creating, removing, and checking
     * theme status in development environment.
     */
    private function registerCommands(): void
    {
        $commands = [
            'theme.generator' => function ($app) {
                return new MakeThemeCommand($app->make('config'), $app->make('files'));
            },
            'theme.remover' => function ($app) {
                return new RemoveThemeCommand($app->make('config'), $app->make('files'));
            },
            'theme.status' => function ($app) {
                return new ThemeStatusCommand;
            },
        ];

        foreach ($commands as $name => $factory) {
            $this->app->singleton($name, $factory);
        }

        $this->commands(array_keys($commands));
    }

    /**
     * Create domain container adapter instance.
     *
     * Factory method to create the adapter that bridges the domain
     * container interface with Laravel's container implementation.
     *
     * @param  mixed  $app  Laravel application container
     * @return ContainerInterface Domain container adapter instance
     */
    private function createDomainContainer($app): ContainerInterface
    {
        return new DomainContainerAdapter($app);
    }
}
