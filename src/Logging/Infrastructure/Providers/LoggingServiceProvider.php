<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Logging\Application\Services\LoggingService;
use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Pollora\Logging\Infrastructure\Factories\LoggerFactory;

/**
 * Service Provider for registering the Pollora logging system.
 *
 * Configures and registers all necessary services for the logging
 * system in the Laravel dependency injection container.
 *
 * @internal
 */
final class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Register services in the container.
     */
    public function register(): void
    {
        $this->registerFactory();
        $this->registerLogger();
        $this->registerLoggingService();
    }

    /**
     * Bootstrap services after registration.
     */
    public function boot(): void
    {
        $this->mergeLoggingConfig();
    }

    /**
     * Register the logger factory.
     */
    private function registerFactory(): void
    {
        $this->app->singleton(LoggerFactory::class, fn ($app): \Pollora\Logging\Infrastructure\Factories\LoggerFactory => new LoggerFactory($app));
    }

    /**
     * Register LoggerInterface with automatic resolution.
     */
    private function registerLogger(): void
    {
        $this->app->singleton(
            LoggerInterface::class,
            fn ($app) => $app->make(LoggerFactory::class)->create()
        );
    }

    /**
     * Register the application logging service.
     */
    private function registerLoggingService(): void
    {
        $this->app->singleton(LoggingService::class);
    }

    /**
     * Merge the Pollora channel configuration into Laravel.
     *
     * Automatically configures the "pollora" channel with
     * optimized settings for the framework.
     */
    private function mergeLoggingConfig(): void
    {
        $config = $this->app->make('config');

        $polloraChannel = [
            'driver' => 'daily',
            'path' => storage_path('logs/pollora.log'),
            'level' => env('POLLORA_LOG_LEVEL', 'debug'),
            'days' => (int) env('POLLORA_LOG_DAYS', 14),
            'replace_placeholders' => true,
            'permission' => 0644,
        ];

        $channels = $config->get('logging.channels', []);
        $channels['pollora'] = $polloraChannel;

        $config->set('logging.channels', $channels);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            LoggerFactory::class,
            LoggerInterface::class,
            LoggingService::class,
        ];
    }
}
