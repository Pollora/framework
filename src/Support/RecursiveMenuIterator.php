<?php

declare(strict_types=1);

namespace Pollora\Support;

use Illuminate\Support\Collection;
use WP_Post;

/**
 * Interface to allow easier menu item iteration.
 */
final class RecursiveMenuIterator extends AbstractRecursiveIterator
{
    /**
     * Create a new RecursiveMenuIterator instance.
     *
     * @param  string|Collection  $menu  Menu to get items of
     */
    public function __construct(string|Collection $menu)
    {
        $this->items = $this->initializeItems($menu);
    }

    /**
     * Initialize menu items.
     */
    private function initializeItems(string|Collection $menu): Collection
    {
        if (is_string($menu) && function_exists('wp_get_nav_menu_object')) {
            return $this->getWordPressMenuItems($menu);
        }

        return $menu instanceof Collection ? $menu : collect([]);
    }

    /**
     * Get WordPress menu items.
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
     */
    private function buildMenuTree(Collection $items): void
    {
        $items->each(function ($item) use ($items): void {
            $item->children = $items->where('menu_item_parent', $item->ID)->values();
        });
    }

    public function current(): WP_Post
    {
        return $this->items[$this->current];
    }

    public function hasChildren(): bool
    {
        return ! $this->current()->children->isEmpty();
    }

    public function getChildren(): ?self
    {
        return new self($this->current()->children);
    }
}
