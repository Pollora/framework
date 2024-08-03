<?php

declare(strict_types=1);

/**
 * Class Menus
 */

namespace Pollen\Theme;

use /**
 * The Translater service class.
 */
Pollen\Services\Translater;
use /**
 * Class Action
 *
 *
 * @method static \Pollen\Support\Actions\Action make($action)
 * @method static bool has($action)
 * @method static \Pollen\Support\Actions\Action|array|null get($action = null, $default = null)
 * @method static \Pollen\Support\Actions\Action|array|null require ($action = null, $default = null)
 * @method static \Pollen\Support\Actions\Action|null remove($action = null)
 * @method static \Pollen\Support\Actions\Action clear()
 * @method static \Pollen\Support\Actions\Action register(string $name, callable|null $callback = null)
 * @method static \Pollen\Support\Actions\Action boot($action = null, array $params = [])
 * @method static \Pollen\Support\Actions\Action listen(string $event, callable $callback, int $priority = 0)
 * @method static \Pollen\Support\Actions\Action trigger(string $event, array $params = [], \Closure|null $before = null, \Closure|null $after = null)
 */
Pollen\Support\Facades\Action;
use Pollen\Theme\Contracts\ThemeComponent;

/**
 * Class Menus
 *
 * This class is responsible for registering the theme menus.
 */
class Menus implements ThemeComponent
{
    public function register(): void
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
        $menus = (array) config('theme.menus');
        $translater = new Translater($menus, 'menus');
        $menus = $translater->translate(['*']);

        register_nav_menus($menus);
    }
}
