<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

use WP_User;

/**
 * Event fired when a user's role is changed.
 *
 * This event is triggered when a user's role is modified,
 * either by adding, removing, or changing roles.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserRoleChanged extends UserEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_User $user,
        public readonly array $oldRoles,
        public readonly ?string $newRole
    ) {
        parent::__construct($user);
    }
} 