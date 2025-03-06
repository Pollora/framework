<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Form;

use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEvent;

/**
 * Event fired when a Gravity Forms form is permanently deleted.
 *
 * This event is triggered when a form is permanently removed from the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class FormDeleted extends GravityFormsEvent {}
