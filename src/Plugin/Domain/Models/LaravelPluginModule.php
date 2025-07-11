<?php

declare(strict_types=1);

namespace Pollora\Plugin\Domain\Models;

use Illuminate\Container\Container;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Str;
use Pollora\Plugin\Infrastructure\Services\PluginAutoloader;

/**
 * Laravel-specific plugin module implementation.
 *
 * Extends the base PluginModule to provide Laravel-specific functionality
 * including service provider registration, autoloading, configuration management,
 * and dependency injection integration.
 */
class LaravelPluginModule extends PluginModule
{
    /**
     * Configurations that are delayed until WordPress is ready for translations.
     */
    protected array $delayedConfigs = [];

    /**
     * Create a new LaravelPluginModule instance.
     *
     * @param  string  $name  Plugin name
     * @param  string  $path  Plugin path
     * @param  Container  $app  Laravel application container
     */
    public function __construct(
        string $name,
        string $path,
        protected Container $app
    ) {
        parent::__construct($name, $path);
    }

    /**
     * Get the cached services path for this plugin.
     *
     * @return string Cached services path
     */
    public function getCachedServicesPath(): string
    {
        // Check if we are running on a Laravel Vapor managed instance
        if (! is_null(env('VAPOR_MAINTENANCE_MODE', null))) {
            return Str::replaceLast('config.php', $this->getSnakeName().'_plugin.php', $this->app->getCachedConfigPath());
        }

        return Str::replaceLast('services.php', $this->getSnakeName().'_plugin.php', $this->app->getCachedServicesPath());
    }

    /**
     * Register the plugin's service providers.
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
     * Boot the plugin.
     */
    public function boot(): void
    {
        // Providers are now handled by ModuleBootstrap via the scout
        parent::boot();
    }

    /**
     * Register plugin autoloading using fixed namespace convention.
     */
    public function registerAutoloading(): void
    {
        if ($this->app->bound(PluginAutoloader::class)) {
            $autoloader = $this->app->make(PluginAutoloader::class);
            $autoloader->registerPluginModule($this);
        }
    }

    /**
     * Register plugin aliases.
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
     * Register plugin files.
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
     * Register plugin translations.
     */
    protected function registerTranslations(): void
    {
        $langPath = $this->getPath().'/languages';

        if (is_dir($langPath) && $this->app->bound('translator')) {
            $this->app['translator']->addNamespace($this->getLowerName(), $langPath);
        }
    }

    /**
     * Register plugin configuration.
     */
    protected function registerConfig(): void
    {
        $configPath = $this->getPath().'/config';

        if (! is_dir($configPath)) {
            return;
        }

        $configFiles = glob($configPath.'/*.php');
        $translationDependentConfigs = ['admin-menu.php', 'settings.php'];

        foreach ($configFiles as $configFile) {
            $configName = basename($configFile, '.php');
            $key = 'plugin.'.$configName;
            $fileName = basename($configFile);

            // Delay loading of configs that use translations until WordPress is ready
            // @TODO : make this better
            if (in_array($fileName, $translationDependentConfigs, true)) {
                // Use WordPress hook to delay loading until translations are available
                if (function_exists('add_action')) {
                    add_action('init', function () use ($configFile, $key): void {
                        if (file_exists($configFile)) {
                            $this->app['config']->set($key, require $configFile);
                        }
                    });
                } else {
                    // Fallback: store for later loading
                    $this->delayedConfigs[$key] = $configFile;
                }
            } elseif (file_exists($configFile)) {
                // Load immediately for configs that don't use translations
                $this->app['config']->set($key, require $configFile);
            }
        }
    }

    /**
     * Get plugin service providers.
     *
     * @return array Service providers array
     */
    public function getProviders(): array
    {
        return $this->get('providers', []);
    }

    /**
     * Get plugin aliases.
     *
     * @return array Aliases array
     */
    public function getAliases(): array
    {
        return $this->get('aliases', []);
    }

    /**
     * Get plugin files to load.
     *
     * @return array Files array
     */
    public function getFiles(): array
    {
        return $this->get('files', []);
    }

    /**
     * Find the main service provider for this plugin.
     *
     * This method is kept for compatibility with ModuleManifest but
     * provider discovery is now primarily handled by ServiceProviderScout.
     *
     * @return string|null Main service provider class name
     */
    public function findMainServiceProvider(): ?string
    {
        $possibleClasses = [
            "Plugin\\{$this->getStudlyName()}\\Providers\\PluginServiceProvider",
            "Plugin\\{$this->getStudlyName()}\\PluginServiceProvider",
            // Legacy support for old naming conventions
            "App\\Plugins\\{$this->getStudlyName()}\\Providers\\PluginServiceProvider",
            "App\\Plugins\\{$this->getStudlyName()}\\PluginServiceProvider",
            "Plugins\\{$this->getStudlyName()}\\Providers\\PluginServiceProvider",
        ];

        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Get plugin routes directory.
     *
     * @return string Plugin routes directory path
     */
    public function getRoutesPath(): string
    {
        return $this->getPath().'/routes';
    }

    /**
     * Get plugin views directory.
     *
     * @return string Plugin views directory path
     */
    public function getViewsPath(): string
    {
        return $this->getPath().'/views';
    }

    /**
     * Get plugin config directory.
     *
     * @return string Plugin config directory path
     */
    public function getConfigPath(): string
    {
        return $this->getPath().'/config';
    }

    /**
     * Get plugin migrations directory.
     *
     * @return string Plugin migrations directory path
     */
    public function getMigrationsPath(): string
    {
        return $this->getPath().'/database/migrations';
    }

    /**
     * Get plugin factories directory.
     *
     * @return string Plugin factories directory path
     */
    public function getFactoriesPath(): string
    {
        return $this->getPath().'/database/factories';
    }

    /**
     * Get plugin seeders directory.
     *
     * @return string Plugin seeders directory path
     */
    public function getSeedersPath(): string
    {
        return $this->getPath().'/database/seeders';
    }

    /**
     * Get plugin assets directory.
     *
     * @return string Plugin assets directory path
     */
    public function getAssetsPath(): string
    {
        return $this->getPath().'/assets';
    }

    /**
     * Check if plugin has migrations.
     *
     * @return bool True if plugin has migrations
     */
    public function hasMigrations(): bool
    {
        $migrationsPath = $this->getMigrationsPath();

        return is_dir($migrationsPath) && count(glob($migrationsPath.'/*.php')) > 0;
    }

    /**
     * Check if plugin has routes.
     *
     * @return bool True if plugin has routes
     */
    public function hasRoutes(): bool
    {
        $routesPath = $this->getRoutesPath();

        return is_dir($routesPath) && (
            file_exists($routesPath.'/web.php') ||
            file_exists($routesPath.'/api.php') ||
            file_exists($routesPath.'/admin.php')
        );
    }

    /**
     * Check if plugin has views.
     *
     * @return bool True if plugin has views
     */
    public function hasViews(): bool
    {
        return is_dir($this->getViewsPath());
    }

    /**
     * Get extra path for plugin directory structure.
     *
     * @param  string  $path  Path to append
     * @return string Full path
     */
    public function getExtraPath(string $path): string
    {
        return $this->getPath().($path !== '' && $path !== '0' ? '/'.trim($path, '/') : '');
    }

    /**
     * Get plugin admin directory.
     *
     * @return string Plugin admin directory path
     */
    public function getAdminPath(): string
    {
        return $this->getPath().'/admin';
    }

    /**
     * Get plugin public directory.
     *
     * @return string Plugin public directory path
     */
    public function getPublicPath(): string
    {
        return $this->getPath().'/public';
    }

    /**
     * Get plugin includes directory.
     *
     * @return string Plugin includes directory path
     */
    public function getIncludesPath(): string
    {
        return $this->getPath().'/includes';
    }

    /**
     * Check if plugin has admin functionality.
     *
     * @return bool True if plugin has admin functionality
     */
    public function hasAdmin(): bool
    {
        return is_dir($this->getAdminPath());
    }

    /**
     * Check if plugin has public functionality.
     *
     * @return bool True if plugin has public functionality
     */
    public function hasPublic(): bool
    {
        return is_dir($this->getPublicPath());
    }
}
