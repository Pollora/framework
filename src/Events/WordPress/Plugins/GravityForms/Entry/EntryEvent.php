<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Entry;

/**
 * Base class for all Gravity Forms entry related events.
 *
 * This abstract class provides the foundation for all entry events,
 * containing the entry data.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class EntryEvent
{
    /**
     * Constructor.
     *
     * @param array $entry The entry data
     */
    public function __construct(
        public readonly array $entry
    ) {
    }
} 