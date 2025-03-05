<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

/**
 * Event fired when a blog is archived.
 *
 * This event is triggered when a blog is marked as archived in a WordPress multisite network.
 * Archived blogs are not accessible to visitors but remain in the database.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogArchived extends BlogEvent
{
} 