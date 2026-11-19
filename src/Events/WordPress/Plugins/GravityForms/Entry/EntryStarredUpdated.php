<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Entry;

/**
 * Event fired when a Gravity Forms entry is starred or unstarred.
 *
 * This event is triggered when an entry's starred property is changed in
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class EntryStarredUpdated extends EntryEvent
{
    /**
     * Constructor.
     *
     * @param  array  $entry  The entry data
     * @param  bool  $isStarred  Whether the entry is now starred
     * @param  bool  $wasStarred  Whether the entry was starred before
     */
    public function __construct(
        array $entry,
        public readonly bool $isStarred,
        public readonly bool $wasStarred
    ) {
        parent::__construct($entry);
    }
}
