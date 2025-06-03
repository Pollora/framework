<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\UserSwitching;

use WP_User;

/**
 * Event fired when a user switches back to their original account.
 *
 * This event is triggered when a user who was impersonating another user
 * switches back to their original account.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserSwitchedBack extends UserSwitchingEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_User $user,
        public readonly ?WP_User $oldUser
    ) {
        parent::__construct($user);
    }
}
