<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Menu;

use WP_Term;

/**
 * Base class for all menu-related events.
 *
 * This abstract class provides the foundation for all menu events,
 * containing the menu term object that triggered the event.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class MenuEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly WP_Term $menu
    ) {}
}
