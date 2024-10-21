<?php

declare(strict_types=1);

namespace Pollora\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Lets Laravel know about the configuration files we have to publish.
 */
class ConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            realpath(__DIR__.'/../../config/wordpress.php') => config_path('wordpress.php'),
            realpath(__DIR__.'/../../config/posttypes.php') => config_path('post-types.php'),
            realpath(__DIR__.'/../../config/taxonomies.php') => config_path('taxonomies.php'),
        ]);
    }
}
