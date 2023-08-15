<?php

declare(strict_types=1);

/**
 * Class ThemeSupportServiceProvider
 */

namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Action;

/**
 * Class ThemeSupportServiceProvider
 *
 * A service provider dedicated to theme support.
 */
class ThemeSupportServiceProvider extends ServiceProvider
{
    public function register()
    {
        Action::add('after_setup_theme', [$this, 'addThemeSupport'], 1);
    }

    /**
     * Register all of the site's theme support.
     *
     * @return void
     */
    public function addThemeSupport()
    {
        collect(config('theme.supports'))->each(function ($value, $key) {
            if (is_string($key)) {
                add_theme_support($key, $value);
            } else {
                add_theme_support($value);
            }
        });
    }
}
