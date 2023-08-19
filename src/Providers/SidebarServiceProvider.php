<?php

declare(strict_types=1);

/**
 * Class SidebarServiceProvider
 */

namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Pollen\Services\Translater;
use Pollen\Support\Facades\Action;

/**
 * Class SidebarServiceProvider
 *
 * A service provider for registering sidebars.
 */
class SidebarServiceProvider extends ServiceProvider
{
    public function register()
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
