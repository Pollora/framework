<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Pollen\Theme\Commands\MakeThemeCommand;

/**
 * Provide extra blade directives to aid in WordPress view development.
 */
class ThemeCommandServiceProvider extends \Qirolab\Theme\ThemeServiceProvider
{
    protected $wp_theme;

    protected $theme_root;

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
