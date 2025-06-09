<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\TwoFactor;

use WP_User;

/**
 * Event fired when a Two Factor authentication attempt fails.
 *
 * This event is triggered when a user fails to complete the Two Factor authentication process,
 * providing information about the error that occurred.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class TwoFactorAuthenticationFailed extends TwoFactorEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_User $user,
        public readonly string $errorCode,
        public readonly string $errorMessage
    ) {
        parent::__construct($user);
    }
}
