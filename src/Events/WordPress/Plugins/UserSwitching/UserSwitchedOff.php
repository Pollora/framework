<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\UserSwitching;

/**
 * Event fired when a user switches off (logs out of the switched account).
 *
 * This event is triggered when a user who was impersonating another user
 * switches off without switching back to their original account.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserSwitchedOff extends UserSwitchingEvent {}
