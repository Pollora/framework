<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Media;

use WP_Post;

/**
 * Base class for all media-related events.
 *
 * This abstract class provides the foundation for all media events,
 * containing the attachment post object that triggered the event.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class MediaEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly WP_Post $attachment
    ) {}
}
