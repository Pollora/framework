<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Confirmation;

/**
 * Event fired when a new Gravity Forms confirmation is created.
 *
 * This event is triggered when a new confirmation is created for a form
 * in the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class ConfirmationCreated extends ConfirmationEvent {}
