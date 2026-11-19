<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Form;

use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEvent;

/**
 * Event fired when a Gravity Forms form is deactivated.
 *
 * This event is triggered when an active form is marked as inactive in
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class FormDeactivated extends GravityFormsEvent {}
