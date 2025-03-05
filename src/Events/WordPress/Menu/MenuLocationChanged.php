<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Menu;

use WP_Term;

/**
 * Event fired when a navigation menu's location is changed.
 *
 * This event is triggered when a menu is assigned to or removed
 * from a theme location.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MenuLocationChanged extends MenuEvent
{
    /**
     * @param WP_Term $menu The menu that was changed
     * @param string $location The theme location identifier
     * @param bool $assigned Whether the menu was assigned (true) or unassigned (false)
     */
    public function __construct(
        WP_Term $menu,
        private readonly string $location,
        private readonly bool $assigned
    ) {
        parent::__construct($menu);
    }

    /**
     * Get the theme location identifier.
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * Check if the menu was assigned or unassigned.
     */
    public function isAssigned(): bool
    {
        return $this->assigned;
    }
} 