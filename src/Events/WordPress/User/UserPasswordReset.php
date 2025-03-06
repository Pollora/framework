<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

/**
 * Event fired when a user's password is reset.
 *
 * This event is triggered when a user successfully resets
 * their password through the password reset process.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserPasswordReset extends UserEvent {}
