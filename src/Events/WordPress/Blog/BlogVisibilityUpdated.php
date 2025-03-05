<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

use WP_Site;

/**
 * Event fired when a blog's visibility is updated.
 *
 * This event is triggered when a blog's public visibility setting is changed
 * in a WordPress multisite network. The visibility value determines whether
 * the blog is publicly accessible or private.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogVisibilityUpdated extends BlogEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_Site $site,
        public readonly string $visibility
    ) {
        parent::__construct($site);
    }
} 