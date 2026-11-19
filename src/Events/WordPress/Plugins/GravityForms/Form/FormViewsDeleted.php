<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Form;

use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEvent;

/**
 * Event fired when the views of a Gravity Forms form are deleted.
 *
 * This event is triggered when the view counter of a form is reset in
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class FormViewsDeleted extends GravityFormsEvent {}
