<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\UserSwitching;

use WP_User;

/**
 * Event fired when a user switches to another user.
 *
 * This event is triggered when an administrator or other privileged user
 * switches their account to impersonate another user.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserSwitchedTo extends UserSwitchingEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_User $user,
        public readonly WP_User $oldUser
    ) {
        parent::__construct($user);
    }
}
