<?php

declare(strict_types=1);

namespace Pollora\Admin;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * WordPress administration panel extension helper.
 *
 * This class provides methods to extend the WordPress administration panel
 * by adding custom menu pages and subpages. It integrates Laravel's dependency
 * injection and routing system with WordPress admin pages.
 */
class Page
{
    /**
     * Creates a new Page instance.
     *
     * @param Container $container Laravel's service container for dependency injection
     */
    public function __construct(
        private readonly Container $container
    ) {}

    /**
     * Add a top-level menu page to the WordPress admin panel.
     *
     * @param string $pageTitle The text to be displayed in the title tags of the page
     * @param string $menuTitle The text to be used for the menu
     * @param string $capability The capability required for this menu to be displayed to the user
     * @param string $slug The slug name to refer to this menu by
     * @param mixed $action The function to be called to output the content for this page
     * @param string $iconUrl The URL to the icon to be used for this menu
     * @param int|null $position The position in the menu order this item should appear
     * @return string The resulting page's hook_suffix
     *
     * @see add_menu_page()
     */
    public function addPage(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $slug,
        mixed $action,
        string $iconUrl = '',
        ?int $position = null
    ): string {
        return add_menu_page(
            $pageTitle,
            $menuTitle,
            $capability,
            $slug,
            $this->wrap($action),
            $iconUrl,
            $position
        );
    }

    /**
     * Add a submenu page to an existing WordPress admin menu.
     *
     * @param string $parent The slug name for the parent menu
     * @param string $pageTitle The text to be displayed in the title tags of the page
     * @param string $menuTitle The text to be used for the menu
     * @param string $capabilities The capability required for this menu to be displayed to the user
     * @param string $slug The slug name to refer to this menu by
     * @param mixed $action The function to be called to output the content for this page
     * @return string|false The resulting page's hook_suffix, or false if the menu cannot be added
     *
     * @see add_submenu_page()
     */
    public function addSubpage(
        string $parent,
        string $pageTitle,
        string $menuTitle,
        string $capabilities,
        string $slug,
        mixed $action
    ): string|false {
        return add_submenu_page(
            $parent,
            $pageTitle,
            $menuTitle,
            $capabilities,
            $slug,
            $this->wrap($action)
        );
    }

    /**
     * Wraps a callback to enable Laravel's dependency injection and response handling.
     *
     * This method allows the use of Laravel's container to resolve dependencies
     * and handle responses in WordPress admin pages, providing a more Laravel-like
     * development experience.
     *
     * @param mixed $callback The callback to wrap
     * @return Closure The wrapped callback
     */
    protected function wrap(mixed $callback): Closure
    {
        return function () use ($callback) {
            $response = $this->container->call($callback);
            $request = $this->container->make(Request::class);

            return Route::prepareResponse($request, $response)->sendContent();
        };
    }
}