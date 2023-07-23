<?php

declare(strict_types=1);

/**
 * Class MenuServiceProvider
 * @package Pollen\Providers
 */
namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Action;
use Pollen\Services\Translater;

/**
 * Class MenuServiceProvider
 *
 * A service provider for registering menus.
 */
class MenuServiceProvider extends ServiceProvider
{
    public function register()
    {
            Action::add('after_setup_theme', [$this, 'registerMenus'], 1);
    }

    /**
     * Register all of the site's theme menus.
     *
     * @return void
     */
    public function registerMenus()
    {
        $menus = config('theme.menus');
        $translater = new Translater($menus, 'menus');
        $menus = $translater->translate(['*']);

        register_nav_menus($menus);
    }
}
