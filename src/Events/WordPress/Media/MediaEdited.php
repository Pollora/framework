<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Media;

use WP_Post;

/**
 * Event fired when an image is edited using the WordPress image editor.
 *
 * This event is triggered when modifications are made to an image file
 * using the WordPress built-in image editor.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MediaEdited extends MediaEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_Post $attachment,
        public readonly string $filename
    ) {
        parent::__construct($attachment);
    }
}
