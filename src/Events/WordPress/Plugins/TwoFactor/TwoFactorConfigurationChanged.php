<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\TwoFactor;

use WP_User;

/**
 * Event fired when a user's Two Factor configuration is changed.
 *
 * This event is triggered when a user modifies their Two Factor settings,
 * such as enabling/disabling providers or updating backup codes.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class TwoFactorConfigurationChanged extends TwoFactorEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_User $user,
        public readonly string $action,
        public readonly string $provider,
        public readonly ?array $oldValue = null,
        public readonly ?array $newValue = null
    ) {
        parent::__construct($user);
    }
}
