<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Menu;

/**
 * Event fired when a navigation menu is deleted.
 *
 * This event is triggered when a menu is permanently removed
 * from the WordPress navigation menu system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MenuDeleted extends MenuEvent {}
