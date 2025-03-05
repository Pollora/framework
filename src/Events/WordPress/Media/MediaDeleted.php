<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Media;

/**
 * Event fired when a media attachment is deleted.
 *
 * This event is triggered when a media file is permanently removed
 * from the WordPress media library.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MediaDeleted extends MediaEvent
{
} 