<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

/**
 * Event fired when a blog is deleted.
 *
 * This event is triggered when a blog is permanently deleted from a WordPress multisite network.
 * The site object provided contains the last known state of the blog before deletion.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogDeleted extends BlogEvent
{
} 