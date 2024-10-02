<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Pollen\Support\Facades\Action;
use Pollen\Theme\Contracts\ThemeComponent;

/**
 * Class Support
 *
 * The Support class is responsible for registering all of the site's theme support.
 */
class Support implements ThemeComponent
{
    public function register(): void
    {
        Action::add('after_setup_theme', $this->addThemeSupport(...), 1);
    }

    /**
     * Register all of the site's theme support.
     */
    public function addThemeSupport(): void
    {
        collect(config('theme.supports'))->each(function ($value, $key): void {
            if (is_string($key)) {
                add_theme_support($key, $value);
            } else {
                add_theme_support($value);
            }
        });
    }
}
