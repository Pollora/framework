<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

/**
 * Event fired when a blog is marked as spam.
 *
 * This event is triggered when a blog is flagged as spam in a WordPress multisite network.
 * Spam blogs are typically restricted or hidden from public view.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogMarkedAsSpam extends BlogEvent
{
} 