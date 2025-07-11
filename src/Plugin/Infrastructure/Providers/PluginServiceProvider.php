<?php

declare(strict_types=1);

namespace Pollora\Plugin\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Application\Services\PluginRegistrar;
use Pollora\Plugin\Infrastructure\Repositories\PluginRepository;
use Pollora\Plugin\Infrastructure\Services\PluginAutoloader;
use Pollora\Plugin\Infrastructure\Services\WordPressPluginParser;
use Pollora\Plugin\UI\Console\Commands\PluginListCommand;
use Pollora\Plugin\UI\Console\Commands\PluginStatusCommand;
use Pollora\Plugin\UI\Console\MakePluginCommand;

/**
 * Plugin service provider.
 *
 * Registers plugin-related services, repositories, and managers with the
 * Laravel application container. Handles the initialization of plugin
 * management infrastructure.
 */
class PluginServiceProvider extends ServiceProvider
{
    /**
     * Register plugin services.
     */
    public function register(): void
    {
        $this->registerPluginAutoloader();
        $this->registerPluginParser();
        $this->registerPluginRepository();
        $this->registerPluginManager();
        $this->registerPluginRegistrar();
        $this->registerPluginCommands();
    }

    /**
     * Boot plugin services.
     */
    public function boot(): void
    {
        $this->registerPluginConfiguration();
        $this->bootPluginAutoloader();
    }

    /**
     * Register plugin autoloader service.
     */
    protected function registerPluginAutoloader(): void
    {
        $this->app->singleton(PluginAutoloader::class, function ($app): PluginAutoloader {
            return new PluginAutoloader($app);
        });
    }

    /**
     * Register plugin parser service.
     */
    protected function registerPluginParser(): void
    {
        $this->app->singleton(WordPressPluginParser::class, function ($app): WordPressPluginParser {
            return new WordPressPluginParser;
        });
    }

    /**
     * Register plugin repository.
     */
    protected function registerPluginRepository(): void
    {
        $this->app->singleton(PluginRepository::class, function ($app): PluginRepository {
            return new PluginRepository(
                $app,
                $app->make(WordPressPluginParser::class)
            );
        });

        // Bind as ModuleRepositoryInterface when specifically for plugins
        $this->app->bind('plugin.repository', function ($app): PluginRepository {
            return $app->make(PluginRepository::class);
        });
    }

    /**
     * Register plugin manager service.
     */
    protected function registerPluginManager(): void
    {
        $this->app->singleton(PluginManager::class, function ($app): PluginManager {
            return new PluginManager(
                $app,
                $app->bound('translator') ? $app->make('translator') : null,
                $app->make(PluginRepository::class)
            );
        });

        // Register alias for convenience
        $this->app->alias(PluginManager::class, 'plugin.manager');
    }

    /**
     * Register plugin registrar service.
     */
    protected function registerPluginRegistrar(): void
    {
        $this->app->singleton(PluginRegistrar::class, function ($app): PluginRegistrar {
            return new PluginRegistrar(
                $app,
                $app->make(WordPressPluginParser::class)
            );
        });

        // Register alias for convenience
        $this->app->alias(PluginRegistrar::class, 'plugin.registrar');
    }

    /**
     * Register plugin console commands.
     */
    protected function registerPluginCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakePluginCommand::class,
                PluginStatusCommand::class,
                PluginListCommand::class,
            ]);
        }
    }

    /**
     * Register plugin configuration.
     */
    protected function registerPluginConfiguration(): void
    {
        // Merge plugin configuration if config file exists
        $configPath = __DIR__.'/../../config/plugin.php';

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'plugin');
        }
    }

    /**
     * Boot plugin autoloader.
     */
    protected function bootPluginAutoloader(): void
    {
        // Boot the autoloader to register any existing plugins
        if ($this->app->bound(PluginAutoloader::class)) {
            $autoloader = $this->app->make(PluginAutoloader::class);

            // Register autoloader if not already registered
            $autoloader->register();
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            PluginAutoloader::class,
            WordPressPluginParser::class,
            PluginRepository::class,
            PluginManager::class,
            PluginRegistrar::class,
            'plugin.repository',
            'plugin.manager',
            'plugin.registrar',
            MakePluginCommand::class,
            PluginStatusCommand::class,
            PluginListCommand::class,
        ];
    }
}
