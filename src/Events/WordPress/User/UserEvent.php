<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

use WP_User;

/**
 * Base class for all user-related events.
 *
 * This abstract class provides the foundation for all user events,
 * containing the user object that triggered the event.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class UserEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly WP_User $user
    ) {}
}
