<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Entry;

/**
 * Event fired when a Gravity Forms entry is deleted.
 *
 * This event is triggered when an entry is permanently removed from
 * the Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class EntryDeleted extends EntryEvent
{
} 