<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

use WP_User;

/**
 * Event fired when a new user is created.
 *
 * This event is triggered when a new user account is registered,
 * either by self-registration or by an administrator.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserCreated extends UserEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_User $user,
        public readonly ?WP_User $creator = null
    ) {
        parent::__construct($user);
    }
}
