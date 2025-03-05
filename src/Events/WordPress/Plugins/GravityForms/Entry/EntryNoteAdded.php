<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Entry;

/**
 * Event fired when a note is added to a Gravity Forms entry.
 *
 * This event is triggered when a note is added to an entry in
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class EntryNoteAdded extends EntryEvent
{
    /**
     * Constructor.
     *
     * @param int $noteId The note ID
     * @param array $entry The entry data
     * @param int $userId The user ID who added the note
     * @param string $userName The username who added the note
     * @param string $note The note content
     * @param string $noteType The note type
     */
    public function __construct(
        public readonly int $noteId,
        array $entry,
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $note,
        public readonly string $noteType
    ) {
        parent::__construct($entry);
    }
} 