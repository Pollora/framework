<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Entry;

/**
 * Event fired when a Gravity Forms entry status is updated.
 *
 * This event is triggered when an entry's status is changed in
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class EntryStatusUpdated extends EntryEvent
{
    /**
     * Constructor.
     *
     * @param  array  $entry  The entry data
     * @param  string  $status  The new status
     * @param  string  $previousStatus  The previous status
     */
    public function __construct(
        array $entry,
        public readonly string $status,
        public readonly string $previousStatus
    ) {
        parent::__construct($entry);
    }
}
