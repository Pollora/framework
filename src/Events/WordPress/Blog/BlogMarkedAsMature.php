<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

/**
 * Event fired when a blog is marked as mature.
 *
 * This event is triggered when a blog is flagged as containing mature content
 * in a WordPress multisite network. This typically affects how the blog is displayed
 * and who can access it.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogMarkedAsMature extends BlogEvent
{
} 