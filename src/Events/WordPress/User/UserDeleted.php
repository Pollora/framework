<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

use WP_User;

/**
 * Event fired when a user is deleted.
 *
 * This event is triggered when a user account is permanently
 * deleted from WordPress.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserDeleted extends UserEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_User $user,
        public readonly array $roles
    ) {
        parent::__construct($user);
    }
}
