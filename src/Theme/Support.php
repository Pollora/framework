<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Pollen\Support\Facades\Action;

/**
 * Class Support
 *
 * The Support class is responsible for registering all of the site's theme support.
 */
class Support
{
    public function init()
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
