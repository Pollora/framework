<?php

declare(strict_types=1);

/**
 * Class ThemeCommandServiceProvider
 *
 * Override the default service provider for the theme command.
 */

namespace Pollen\Theme;

use Pollen\Theme\Commands\MakeThemeCommand;

class ThemeCommandServiceProvider extends \Qirolab\Theme\ThemeServiceProvider
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
