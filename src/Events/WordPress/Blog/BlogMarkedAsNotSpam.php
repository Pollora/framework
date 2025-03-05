<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

/**
 * Event fired when a blog is marked as not spam.
 *
 * This event is triggered when a blog is unflagged as spam in a WordPress multisite network.
 * The blog regains normal visibility and functionality.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogMarkedAsNotSpam extends BlogEvent
{
} 