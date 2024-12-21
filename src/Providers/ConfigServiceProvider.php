<?php

declare(strict_types=1);

namespace Pollora\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for publishing Pollora configuration files.
 *
 * This provider handles the publication of configuration files necessary for
 * WordPress integration with Laravel, including:
 * - WordPress core configuration
 * - Custom post types configuration
 * - Custom taxonomies configuration
 *
 * These files will be published to the Laravel config directory when running
 * the vendor:publish command.
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the configuration files.
     *
     * Registers configuration files that can be published to the Laravel
     * application's config directory. This includes:
     * - wordpress.php: Core WordPress integration settings
     * - post-types.php: Custom post type definitions
     * - taxonomies.php: Custom taxonomy definitions
     *
     * @return void
     *
     * @example
     * // To publish these configurations, run:
     * // php artisan vendor:publish --provider="Pollora\Providers\ConfigServiceProvider"
     */
    public function boot(): void
    {
        $this->publishes([
            realpath(__DIR__.'/../../config/wordpress.php') => config_path('wordpress.php'),
            realpath(__DIR__.'/../../config/posttypes.php') => config_path('post-types.php'),
            realpath(__DIR__.'/../../config/taxonomies.php') => config_path('taxonomies.php'),
        ]);
    }
}
