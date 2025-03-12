<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

/**
 * Event fired when a user requests a password reset.
 *
 * This event is triggered when a user initiates the "Lost Password"
 * process by requesting a password reset link.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserPasswordRequested extends UserEvent {}
