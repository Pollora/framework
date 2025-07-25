<?php

declare(strict_types=1);

namespace Pollora\Exceptions\Infrastructure\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Pollora\Exceptions\Infrastructure\Handlers\ModuleAwareExceptionHandler;
use Pollora\Exceptions\Infrastructure\Services\ModuleAwareErrorViewResolver;

/**
 * Service provider for module-aware exception handling.
 *
 * This service provider registers the custom exception handler and related
 * services that enable module-aware error view resolution. It integrates
 * with Laravel's exception handling system to provide enhanced error page
 * discovery from registered modules.
 *
 * The provider registers:
 * - ModuleAwareExceptionHandler as the default exception handler
 * - ModuleAwareErrorViewResolver for error view discovery
 * - Configuration bindings for exception handling behavior
 *
 * @author Pollora Framework
 */
class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Register exception handling services.
     *
     * Registers the module-aware exception handler and error view resolver
     * with the service container, replacing Laravel's default exception handler.
     */
    public function register(): void
    {
        $this->registerErrorViewResolver();
        $this->registerExceptionHandler();
        $this->mergeExceptionConfiguration();
    }

    /**
     * Bootstrap exception handling services.
     *
     * Performs any necessary bootstrapping for the exception handling system.
     * This method is called after all service providers have been registered.
     */
    public function boot(): void
    {
        $this->publishExceptionConfiguration();
    }

    /**
     * Register the module-aware error view resolver.
     *
     * Binds the ModuleAwareErrorViewResolver as a singleton in the container,
     * making it available for dependency injection and reuse across requests.
     */
    protected function registerErrorViewResolver(): void
    {
        $this->app->singleton(ModuleAwareErrorViewResolver::class, function ($app) {
            /** @var \Illuminate\Contracts\View\Factory $viewFactory */
            $viewFactory = $app->make('view');
            
            return new ModuleAwareErrorViewResolver($app, $viewFactory);
        });
    }

    /**
     * Register the module-aware exception handler.
     *
     * Replaces Laravel's default exception handler with the module-aware
     * implementation that supports enhanced error view resolution from modules.
     */
    protected function registerExceptionHandler(): void
    {
        $this->app->singleton(ExceptionHandler::class, function ($app) {
            return new ModuleAwareExceptionHandler($app);
        });
    }

    /**
     * Merge exception handling configuration.
     *
     * Merges framework-specific exception handling configuration with the
     * application's existing configuration, providing sensible defaults.
     */
    protected function mergeExceptionConfiguration(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/exceptions.php',
            'pollora.exceptions'
        );
    }

    /**
     * Publish exception handling configuration.
     *
     * Makes the exception handling configuration available for customization
     * by application developers through the artisan publish command.
     */
    protected function publishExceptionConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/exceptions.php' => config_path('pollora/exceptions.php'),
            ], 'pollora-exceptions');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * Returns an array of service names that this provider registers,
     * used by Laravel for deferred service provider loading.
     *
     * @return array<int, string>  Array of provided service names
     */
    public function provides(): array
    {
        return [
            ExceptionHandler::class,
            ModuleAwareExceptionHandler::class,
            ModuleAwareErrorViewResolver::class,
        ];
    }
}