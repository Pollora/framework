<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Entry;

/**
 * Event fired when a note of a Gravity Forms entry is about to be deleted.
 *
 * This event is triggered before a note is deleted from an entry in
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class EntryNoteDeleted extends EntryEvent
{
    /**
     * Constructor.
     *
     * @param  int  $noteId  The ID of the note being deleted
     * @param  array  $entry  The entry data
     */
    public function __construct(
        public readonly int $noteId,
        array $entry
    ) {
        parent::__construct($entry);
    }
}
