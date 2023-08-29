<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Pollen\Services\Translater;
use Pollen\Support\Facades\Action;

/**
 * Class Sidebar
 *
 * This class is responsible for registering theme sidebars.
 */
class Sidebar
{
    public function init()
    {
        Action::add('after_setup_theme', [$this, 'registerSidebars'], 1);
    }

    /**
     * Register all of the site's theme sidebars.
     *
     * @return void
     */
    public function registerSidebars()
    {
        $sidebars = (array) config('theme.sidebars');
        $translater = new Translater($sidebars, 'sidebars');
        $sidebars = $translater->translate(['*.name', '*.description']);

        collect($sidebars)->each(function ($value) {
            register_sidebar($value);
        });
    }
}
