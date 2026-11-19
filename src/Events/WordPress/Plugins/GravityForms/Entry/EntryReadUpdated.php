<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Entry;

/**
 * Event fired when a Gravity Forms entry is marked as read or unread.
 *
 * This event is triggered when an entry's read property is changed in
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class EntryReadUpdated extends EntryEvent
{
    /**
     * Constructor.
     *
     * @param  array  $entry  The entry data
     * @param  bool  $isRead  Whether the entry is now read
     * @param  bool  $wasRead  Whether the entry was read before
     */
    public function __construct(
        array $entry,
        public readonly bool $isRead,
        public readonly bool $wasRead
    ) {
        parent::__construct($entry);
    }
}
