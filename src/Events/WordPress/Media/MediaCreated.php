<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Media;

/**
 * Event fired when a new media attachment is created.
 *
 * This event is triggered when a new file is uploaded to the WordPress media library
 * or when a file is attached to a post.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MediaCreated extends MediaEvent {}
