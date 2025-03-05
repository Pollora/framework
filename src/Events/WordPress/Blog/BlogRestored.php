<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

/**
 * Event fired when a blog is restored from trash.
 *
 * This event is triggered when a blog is restored from the trash in a WordPress multisite network.
 * The blog becomes accessible again after restoration.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogRestored extends BlogEvent
{
} 