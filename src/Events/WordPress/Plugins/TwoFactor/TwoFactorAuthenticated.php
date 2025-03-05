<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\TwoFactor;

use WP_User;

/**
 * Event fired when a user successfully authenticates using Two Factor authentication.
 *
 * This event is triggered after a user completes the Two Factor authentication process,
 * providing information about the authentication method used.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class TwoFactorAuthenticated extends TwoFactorEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_User $user,
        public readonly string $provider
    ) {
        parent::__construct($user);
    }
} 