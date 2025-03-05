<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Media;

/**
 * Event fired when a media attachment is updated.
 *
 * This event is triggered when an existing media file's metadata or details
 * are modified in the WordPress media library.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MediaUpdated extends MediaEvent
{
} 