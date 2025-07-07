<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Illuminate\Container\Container;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Str;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;

class LaravelThemeModule extends ThemeModule
{
    /**
     * Configurations that are delayed until WordPress is ready for translations.
     */
    protected array $delayedConfigs = [];

    public function __construct(
        string $name,
        string $path,
        protected Container $app
    ) {
        parent::__construct($name, $path);
    }

    /**
     * Get the cached services path for this theme.
     */
    public function getCachedServicesPath(): string
    {
        // Check if we are running on a Laravel Vapor managed instance
        if (! is_null(env('VAPOR_MAINTENANCE_MODE', null))) {
            return Str::replaceLast('config.php', $this->getSnakeName().'_theme.php', $this->app->getCachedConfigPath());
        }

        return Str::replaceLast('services.php', $this->getSnakeName().'_theme.php', $this->app->getCachedServicesPath());
    }

    /**
     * Register the theme's service providers.
     */
    public function register(): void
    {
        $this->registerAliases();
        $this->registerFiles();
        $this->registerTranslations();
        $this->registerConfig();

        parent::register();
    }

    /**
     * Boot the theme.
     */
    public function boot(): void
    {
        // Providers are now handled by ModuleBootstrap via the scout
        parent::boot();
    }

    /**
     * Register theme autoloading using fixed namespace convention.
     */
    public function registerAutoloading(): void
    {
        if ($this->app->bound(ThemeAutoloader::class)) {
            $autoloader = $this->app->make(ThemeAutoloader::class);
            $autoloader->registerThemeModule($this);
        }
    }

    /**
     * Register theme aliases.
     */
    protected function registerAliases(): void
    {
        // Skip alias registration if AliasLoader is not available (e.g., in tests)
        if (! class_exists(AliasLoader::class)) {
            return;
        }

        $loader = AliasLoader::getInstance();
        $aliases = $this->getAliases();

        foreach ($aliases as $aliasName => $aliasClass) {
            $loader->alias($aliasName, $aliasClass);
        }
    }

    /**
     * Register theme files.
     */
    protected function registerFiles(): void
    {
        $files = $this->getFiles();

        foreach ($files as $file) {
            $filePath = $this->getPath().'/'.$file;
            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }
    }

    /**
     * Register theme translations.
     */
    protected function registerTranslations(): void
    {
        $langPath = $this->getPath().'/languages';

        if (is_dir($langPath) && $this->app->bound('translator')) {
            $this->app['translator']->addNamespace($this->getLowerName(), $langPath);
        }
    }

    /**
     * Register theme configuration.
     */
    protected function registerConfig(): void
    {
        $configPath = $this->getPath().'/config';

        if (! is_dir($configPath)) {
            return;
        }

        $configFiles = glob($configPath.'/*.php');
        $translationDependentConfigs = ['menus.php', 'sidebars.php', 'templates.php'];

        foreach ($configFiles as $configFile) {
            $configName = basename($configFile, '.php');
            $key = 'theme.'.$configName;
            $fileName = basename($configFile);

            // Delay loading of configs that use translations until WordPress is ready
            if (in_array($fileName, $translationDependentConfigs, true)) {
                // Use WordPress hook to delay loading until translations are available
                if (function_exists('add_action')) {
                    add_action('init', function () use ($configFile, $key) {
                        if (file_exists($configFile)) {
                            $this->app['config']->set($key, require $configFile);
                        }
                    });
                } else {
                    // Fallback: store for later loading
                    $this->delayedConfigs[$key] = $configFile;
                }
            } else {
                // Load immediately for configs that don't use translations
                if (file_exists($configFile)) {
                    $this->app['config']->set($key, require $configFile);
                }
            }
        }
    }

    /**
     * Get theme service providers.
     */
    public function getProviders(): array
    {
        return $this->get('providers', []);
    }

    /**
     * Get theme aliases.
     */
    public function getAliases(): array
    {
        return $this->get('aliases', []);
    }

    /**
     * Get theme files to load.
     */
    public function getFiles(): array
    {
        return $this->get('files', []);
    }

    /**
     * Find the main service provider for this theme.
     *
     * This method is kept for compatibility with ModuleManifest but
     * provider discovery is now primarily handled by ServiceProviderScout.
     */
    public function findMainServiceProvider(): ?string
    {
        $possibleClasses = [
            "Theme\\{$this->getStudlyName()}\\Providers\\ThemeServiceProvider",
            "Theme\\{$this->getStudlyName()}\\ThemeServiceProvider",
            // Legacy support for old naming conventions
            "App\\Themes\\{$this->getStudlyName()}\\Providers\\ThemeServiceProvider",
            "App\\Themes\\{$this->getStudlyName()}\\ThemeServiceProvider",
            "Themes\\{$this->getStudlyName()}\\Providers\\ThemeServiceProvider",
        ];

        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Get theme routes directory.
     */
    public function getRoutesPath(): string
    {
        return $this->getPath().'/routes';
    }

    /**
     * Get theme views directory.
     */
    public function getViewsPath(): string
    {
        return $this->getPath().'/views';
    }

    /**
     * Get theme config directory.
     */
    public function getConfigPath(): string
    {
        return $this->getPath().'/config';
    }

    /**
     * Get theme migrations directory.
     */
    public function getMigrationsPath(): string
    {
        return $this->getPath().'/database/migrations';
    }

    /**
     * Get theme factories directory.
     */
    public function getFactoriesPath(): string
    {
        return $this->getPath().'/database/factories';
    }

    /**
     * Get theme seeders directory.
     */
    public function getSeedersPath(): string
    {
        return $this->getPath().'/database/seeders';
    }

    /**
     * Get theme assets directory.
     */
    public function getAssetsPath(): string
    {
        return $this->getPath().'/assets';
    }

    /**
     * Check if theme has migrations.
     */
    public function hasMigrations(): bool
    {
        $migrationsPath = $this->getMigrationsPath();

        return is_dir($migrationsPath) && count(glob($migrationsPath.'/*.php')) > 0;
    }

    /**
     * Check if theme has routes.
     */
    public function hasRoutes(): bool
    {
        $routesPath = $this->getRoutesPath();

        return is_dir($routesPath) && (
            file_exists($routesPath.'/web.php') ||
            file_exists($routesPath.'/api.php')
        );
    }

    /**
     * Check if theme has views.
     */
    public function hasViews(): bool
    {
        return is_dir($this->getViewsPath());
    }

    /**
     * Get extra path for theme directory structure.
     */
    public function getExtraPath(string $path): string
    {
        return $this->getPath().($path ? '/'.trim($path, '/') : '');
    }
}
