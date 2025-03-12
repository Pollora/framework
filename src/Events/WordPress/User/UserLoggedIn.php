<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

/**
 * Event fired when a user logs in.
 *
 * This event is triggered when a user successfully authenticates
 * and logs into WordPress.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserLoggedIn extends UserEvent {}
