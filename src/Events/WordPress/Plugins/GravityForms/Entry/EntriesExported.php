<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Entry;

use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEvent;

/**
 * Event fired when the entries of a Gravity Forms form are exported.
 *
 * This event is triggered after the entries of a form have been exported
 * in the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class EntriesExported extends GravityFormsEvent
{
    /**
     * Constructor.
     *
     * @param  array  $form  The form the entries come from
     * @param  string  $startDate  The start date of the export
     * @param  string  $endDate  The end date of the export
     * @param  array|string  $fields  The exported fields
     * @param  string  $exportId  The unique ID of the export
     */
    public function __construct(
        array $form,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly array|string $fields,
        public readonly string $exportId
    ) {
        parent::__construct($form);
    }
}
