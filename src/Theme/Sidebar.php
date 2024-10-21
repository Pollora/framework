<?php

declare(strict_types=1);

namespace Pollora\Theme;

use Pollora\Services\Translater;
use Pollora\Support\Facades\Action;
use Pollora\Theme\Contracts\ThemeComponent;

/**
 * Class Sidebar
 *
 * This class is responsible for registering theme sidebars.
 */
class Sidebar implements ThemeComponent
{
    public function register(): void
    {
        Action::add('after_setup_theme', $this->registerSidebars(...), 1);
    }

    /**
     * Register all of the site's theme sidebars.
     */
    public function registerSidebars(): void
    {
        $sidebars = (array) config('theme.sidebars');
        $translater = new Translater($sidebars, 'sidebars');
        $sidebars = $translater->translate(['*.name', '*.description']);

        collect($sidebars)->each(function ($value): void {
            register_sidebar($value);
        });
    }
}
