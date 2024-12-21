<?php

declare(strict_types=1);

namespace Pollora\Admin;

/**
 * Factory for creating WordPress admin pages with a fluent interface.
 *
 * This class provides a fluent interface for creating WordPress admin pages
 * and subpages, making it easier to configure and manage admin menus
 * in a Laravel-like way.
 */
class PageFactory
{
    /**
     * Creates a new PageFactory instance.
     *
     * @param Page $page The Page instance used to create admin pages
     */
    public function __construct(private readonly Page $page) {}

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
     * @return self Returns the factory instance for method chaining
     *
     * @see Page::addPage()
     */
    public function page(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $slug,
        mixed $action,
        string $iconUrl = '',
        ?int $position = null
    ): self {
        $this->page->addPage($pageTitle, $menuTitle, $capability, $slug, $action, $iconUrl, $position);

        return $this;
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
     * @return self Returns the factory instance for method chaining
     *
     * @see Page::addSubpage()
     */
    public function subpage(
        string $parent,
        string $pageTitle,
        string $menuTitle,
        string $capabilities,
        string $slug,
        mixed $action
    ): self {
        $this->page->addSubpage($parent, $pageTitle, $menuTitle, $capabilities, $slug, $action);

        return $this;
    }
}
