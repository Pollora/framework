<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\UserSwitching;

use WP_User;

/**
 * Base class for all User Switching events.
 *
 * This abstract class provides the foundation for all User Switching events,
 * containing the user object that triggered the event.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class UserSwitchingEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly WP_User $user
    ) {
    }
} 