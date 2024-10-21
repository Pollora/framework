<?php

declare(strict_types=1);

/**
 * Class ThemeCommandProvider
 *
 * Override the default service provider for the theme command.
 */

namespace Pollora\Theme;

use Pollora\Theme\Commands\MakeThemeCommand;

class ThemeCommandProvider extends \Qirolab\Theme\ThemeServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/theme.php' => config_path('theme.php'),
            ], 'config');

            $this->commands([
                MakeThemeCommand::class,
            ]);
        }
    }
}
