<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

/**
 * Event fired when a blog is moved to trash.
 *
 * This event is triggered when a blog is moved to the trash in a WordPress multisite network.
 * Trashed blogs are not accessible but can be restored.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogTrashed extends BlogEvent
{
} 