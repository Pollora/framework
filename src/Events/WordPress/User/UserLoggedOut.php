<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

/**
 * Event fired when a user logs out.
 *
 * This event is triggered when a user logs out of WordPress
 * or their session is terminated.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserLoggedOut extends UserEvent {}
