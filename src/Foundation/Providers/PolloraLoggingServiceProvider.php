<?php

namespace Pollora\Foundation\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Pollora Logging Service Provider
 *
 * Registers default logging channels for the Pollora framework.
 * These channels are merged with the application's logging configuration.
 *
 * @package Pollora\Foundation\Providers
 */
class PolloraLoggingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * Registers default logging channels for Pollora if they don't exist
     * in the application's configuration.
     *
     * @return void
     */
    public function register(): void
    {
        // Get existing logging channels
        $existingChannels = config('logging.channels', []);

        // Get Pollora's default channels
        $polloraChannels = $this->getDefaultLoggingChannels();

        // Merge channels (existing config takes precedence)
        $mergedChannels = array_merge($polloraChannels, $existingChannels);

        // Set the merged configuration
        config(['logging.channels' => $mergedChannels]);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the default logging channels for Pollora.
     *
     * Returns an array of logging channel configurations that will be
     * merged with the application's logging configuration. Users can
     * override these in their own config/logging.php file.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getDefaultLoggingChannels(): array
    {
        return [
            'wordpress' => [
                'driver' => 'single',
                'path' => storage_path('logs/wordpress.log'),
                'level' => env('WORDPRESS_LOG_LEVEL', 'debug'),
                'days' => env('WORDPRESS_LOG_DAYS', 7),
                'replace_placeholders' => true,
            ],
        ];
    }
}
