<?php

declare(strict_types=1);

/**
 * Class Menus
 */

namespace Pollora\Theme;

use /**
 * The Translater service class.
 */
Pollora\Services\Translater;
use /**
 * Class Action
 *
 *
 * @method static \Pollora\Support\Actions\Action make($action)
 * @method static bool has($action)
 * @method static \Pollora\Support\Actions\Action|array|null get($action = null, $default = null)
 * @method static \Pollora\Support\Actions\Action|array|null require ($action = null, $default = null)
 * @method static \Pollora\Support\Actions\Action|null remove($action = null)
 * @method static \Pollora\Support\Actions\Action clear()
 * @method static \Pollora\Support\Actions\Action register(string $name, callable|null $callback = null)
 * @method static \Pollora\Support\Actions\Action boot($action = null, array $params = [])
 * @method static \Pollora\Support\Actions\Action listen(string $event, callable $callback, int $priority = 0)
 * @method static \Pollora\Support\Actions\Action trigger(string $event, array $params = [], \Closure|null $before = null, \Closure|null $after = null)
 */
Pollora\Support\Facades\Action;
use Pollora\Theme\Contracts\ThemeComponent;

/**
 * Class Menus
 *
 * This class is responsible for registering the theme menus.
 */
class Menus implements ThemeComponent
{
    public function register(): void
    {
        Action::add('after_setup_theme', $this->registerMenus(...), 1);
    }

    /**
     * Register all of the site's theme menus.
     */
    public function registerMenus(): void
    {
        $menus = (array) config('theme.menus');
        $translater = new Translater($menus, 'menus');
        $menus = $translater->translate(['*']);

        register_nav_menus($menus);
    }
}
