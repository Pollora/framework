<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Menu;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use WP_Term;

/**
 * Dispatches Laravel events for WordPress menu actions.
 *
 * This dispatcher listens to the following WordPress actions:
 * - wp_create_nav_menu
 * - wp_update_nav_menu
 * - delete_nav_menu
 * - update_option_theme_mods_*
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MenuEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'wp_create_nav_menu',
        'wp_update_nav_menu',
        'delete_nav_menu',
        'update_option',
    ];

    /**
     * Handle menu creation.
     */
    public function handleWpCreateNavMenu(int $menu_id): void
    {
        $menu = get_term($menu_id, 'nav_menu');
        if (! $menu instanceof WP_Term) {
            return;
        }

        $this->dispatch(MenuCreated::class, [$menu]);
    }

    /**
     * Handle menu update.
     */
    public function handleWpUpdateNavMenu(int $menu_id): void
    {
        $menu = get_term($menu_id, 'nav_menu');

        if (! $menu instanceof WP_Term) {
            return;
        }

        $this->dispatch(MenuUpdated::class, [$menu]);
    }

    /**
     * Handle menu deletion.
     */
    public function handleDeleteNavMenu(WP_Term $menu): void
    {
        $this->dispatch(MenuDeleted::class, [$menu]);
    }

    /**
     * Handle menu location changes.
     */
    public function handleUpdateOption(string $option, mixed $oldValue, mixed $value): void
    {
        if (! str_starts_with($option, 'theme_mods_')) {
            return;
        }

        $old_locations = $old_value['nav_menu_locations'] ?? [];
        $new_locations = $value['nav_menu_locations'] ?? [];

        $this->processLocationChanges($old_locations, $new_locations);
    }

    /**
     * Process changes in menu locations.
     */
    private function processLocationChanges(array $old_locations, array $new_locations): void
    {
        $all_locations = array_unique(array_merge(array_keys($old_locations), array_keys($new_locations)));

        foreach ($all_locations as $location) {
            $old_menu_id = $old_locations[$location] ?? 0;
            $new_menu_id = $new_locations[$location] ?? 0;

            if ($old_menu_id !== $new_menu_id) {
                // Handle unassignment of old menu
                if ($old_menu_id > 0) {
                    $old_menu = get_term($old_menu_id, 'nav_menu');
                    if ($old_menu instanceof WP_Term) {
                        $this->dispatch(MenuLocationChanged::class, [$old_menu, $location, false]);
                    }
                }

                // Handle assignment of new menu
                if ($new_menu_id > 0) {
                    $new_menu = get_term($new_menu_id, 'nav_menu');
                    if ($new_menu instanceof WP_Term) {
                        $this->dispatch(MenuLocationChanged::class, [$new_menu, $location, true]);
                    }
                }
            }
        }
    }
}
