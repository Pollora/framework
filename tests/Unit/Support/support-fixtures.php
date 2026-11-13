<?php

declare(strict_types=1);

/*
 * WordPress functions used by Pollora\Support, declared in that namespace so
 * PHP resolves them before the global ones: nothing leaks into the global
 * function table. wp_get_nav_menu_object() alone is also declared globally,
 * because RecursiveMenuIterator checks function_exists() on its global name.
 */

namespace Pollora\Support {
    function pollora_support_call(string $name, array $arguments): mixed
    {
        $callback = $GLOBALS['pollora_support_wordpress'][$name] ?? null;

        if ($callback === null) {
            throw new \LogicException("The test did not provide {$name}().");
        }

        return $callback(...$arguments);
    }

    function is_multisite(...$arguments): mixed
    {
        return pollora_support_call('is_multisite', $arguments);
    }

    function get_current_blog_id(...$arguments): mixed
    {
        return pollora_support_call('get_current_blog_id', $arguments);
    }

    function get_option(...$arguments): mixed
    {
        return pollora_support_call('get_option', $arguments);
    }

    function wp_get_current_user(...$arguments): mixed
    {
        return pollora_support_call('wp_get_current_user', $arguments);
    }

    function get_nav_menu_locations(...$arguments): mixed
    {
        return pollora_support_call('get_nav_menu_locations', $arguments);
    }

    function wp_get_nav_menu_object(...$arguments): mixed
    {
        return pollora_support_call('wp_get_nav_menu_object', $arguments);
    }

    function wp_get_nav_menu_items(...$arguments): mixed
    {
        return pollora_support_call('wp_get_nav_menu_items', $arguments);
    }
}

namespace {
    if (! function_exists('wp_get_nav_menu_object')) {
        function wp_get_nav_menu_object(mixed $menu = null): mixed
        {
            return Pollora\Support\wp_get_nav_menu_object($menu);
        }
    }

    if (! class_exists('WP_Post', false)) {
        eval('class WP_Post { public int $ID = 1; public function __construct($post = null) {} }');
    }

    if (! class_exists('WP_Network', false)) {
        eval('class WP_Network {}');
    }

    if (! class_exists('WP_User', false)) {
        eval('class WP_User { public int $ID = 0; }');
    }
}

namespace Tests\Unit\Support\Fixtures {
    /**
     * @param  array<string, callable>  $functions
     */
    function support_wordpress(array $functions): void
    {
        $GLOBALS['pollora_support_wordpress'] = $functions;
    }

    class FakeMenuItem extends \WP_Post
    {
        public int $menu_item_parent = 0;

        public ?\Illuminate\Support\Collection $children = null;

        public function __construct(int $id = 0, int $parent = 0, public string $title = '')
        {
            $this->ID = $id;
            $this->menu_item_parent = $parent;
            $this->title = $title;
        }
    }
}
