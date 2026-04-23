<?php

declare(strict_types=1);

namespace Pollora\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for publishing Pollora configuration files.
 *
 * Handles the publication of configuration files necessary for
 * WordPress integration with Laravel.
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the configuration files.
     *
     * Registers configuration files that can be published to the Laravel
     * application's config directory.
     */
    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 2).'/config/wordpress.php' => config_path('wordpress.php'),
        ]);
    }
}
