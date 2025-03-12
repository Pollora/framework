<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Post;

use WP_Post;

/**
 * Base class for all post-related events.
 *
 * This abstract class provides the foundation for all post events,
 * containing the post object that triggered the event.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class PostEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly WP_Post $post
    ) {}
}
