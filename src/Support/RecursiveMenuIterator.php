<?php

declare(strict_types=1);

namespace Pollora\Support;

use Illuminate\Support\Collection;
use WP_Post;

/**
 * Iterator for WordPress menu items with recursive navigation support.
 *
 * This class provides an interface for iterating over WordPress menu items,
 * supporting nested menu structures and hierarchical navigation.
 *
 * @extends AbstractRecursiveIterator<int, \WP_Post>
 */
final class RecursiveMenuIterator extends AbstractRecursiveIterator
{
    /**
     * Create a new RecursiveMenuIterator instance.
     *
     * @param  string|Collection  $menu  Menu name or collection of menu items
     */
    public function __construct(string|Collection $menu)
    {
        $this->items = $this->initializeItems($menu);
    }

    /**
     * Initialize menu items from string name or collection.
     *
     * @param  string|Collection  $menu  Menu name or collection
     * @return Collection Collection of menu items
     */
    private function initializeItems(string|Collection $menu): Collection
    {
        if (is_string($menu) && function_exists('wp_get_nav_menu_object')) {
            return $this->getWordPressMenuItems($menu);
        }

        return $menu instanceof Collection ? $menu : collect([]);
    }

    /**
     * Get WordPress menu items by menu name.
     *
     * @param  string  $menuName  Name of the menu in WordPress
     * @return Collection Collection of menu items
     */
    private function getWordPressMenuItems(string $menuName): Collection
    {
        $navLocations = get_nav_menu_locations();

        if (! isset($navLocations[$menuName])) {
            return collect([]);
        }

        $menu = wp_get_nav_menu_object($navLocations[$menuName]);
        $items = collect(wp_get_nav_menu_items($menu))->keyBy('ID')->reverse();

        $this->buildMenuTree($items);

        return $items->filter(fn ($item): bool => $item->menu_item_parent == 0)->reverse()->values();
    }

    /**
     * Build menu tree by assigning children to parent items.
     *
     * @param  Collection  $items  Collection of menu items
     */
    private function buildMenuTree(Collection $items): void
    {
        $items->each(function ($item) use ($items): void {
            $item->children = $items->where('menu_item_parent', $item->ID)->values();
        });
    }

    /**
     * Get the current menu item.
     *
     * @return \WP_Post Current menu item
     */
    public function current(): WP_Post
    {
        return $this->items[$this->current];
    }

    /**
     * Check if current menu item has children.
     *
     * @return bool True if current item has child menu items
     */
    public function hasChildren(): bool
    {
        return ! $this->current()->children->isEmpty();
    }

    /**
     * Get iterator for child menu items.
     *
     * @return self|null Iterator for child items or null if no children
     */
    public function getChildren(): ?self
    {
        return new self($this->current()->children);
    }
}
