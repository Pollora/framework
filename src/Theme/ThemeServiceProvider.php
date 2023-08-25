<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Action;
use Qirolab\Theme\Theme;

/**
 * Provide extra blade directives to aid in WordPress view development.
 */
class ThemeServiceProvider extends ServiceProvider
{
    protected $wp_theme;

    protected $theme_root;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->directives()
            ->each(function ($directive, $function) {
                Blade::directive($function, $directive);
            });
    }

    /**
     * Get the Blade directives.
     *
     * @return array
     */
    public function directives()
    {
        return collect(['Directives'])
            ->flatMap(function ($directive) {
                if (file_exists($directives = __DIR__.'/'.$directive.'.php')) {
                    return require_once $directives;
                }
            });
    }

    public function register()
    {
        $this->theme_root = config('theme.base_path');

        Action::add('init', function () {
            if (wp_installing()) {
                return;
            }
            $this->initializeTheme();
        }, 1);
    }

    private function initializeTheme()
    {
        global $wp_theme_directories;

        register_theme_directory($this->theme_root);

        Theme::set(get_stylesheet());

        $GLOBALS['wp_theme_directories'][] = WP_CONTENT_DIR.'/themes';

        $this->wp_theme = wp_get_theme();

        $this->app->singleton('wp.theme', function () {
            return $this->wp_theme;
        });
    }
}
