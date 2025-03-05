<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Post;

use WP_Post;

/**
 * Event fired when a post is updated.
 *
 * This event is triggered when a post's content or metadata is modified,
 * including status changes that don't fall into other specific categories.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PostUpdated extends PostEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_Post $post,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {
        parent::__construct($post);
    }
}
