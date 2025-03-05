<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Post;

/**
 * Event fired when a post is permanently deleted.
 *
 * This event is triggered when a post is permanently removed from the database,
 * typically after being deleted from the trash.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PostDeleted extends PostEvent
{
} 