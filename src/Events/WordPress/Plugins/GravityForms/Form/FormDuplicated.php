<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Form;

use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEvent;

/**
 * Event fired when a Gravity Forms form is duplicated.
 *
 * This event is triggered when a form is duplicated in the Gravity Forms
 * system. The form property holds the original form.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class FormDuplicated extends GravityFormsEvent
{
    /**
     * Constructor.
     *
     * @param  array  $form  The original form data
     * @param  array  $duplicate  The duplicated form data
     */
    public function __construct(
        array $form,
        public readonly array $duplicate
    ) {
        parent::__construct($form);
    }
}
