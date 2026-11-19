<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Form;

use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEvent;

/**
 * Event fired when a Gravity Forms form is activated.
 *
 * This event is triggered when an inactive form is marked as active in
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class FormActivated extends GravityFormsEvent {}
