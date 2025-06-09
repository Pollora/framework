<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

use WP_Site;

/**
 * Event fired when a new blog is created.
 *
 * This event is triggered when a new blog is initialized in a WordPress multisite network.
 * It provides access to both the new site object and the initialization arguments.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogCreated extends BlogEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_Site $site,
        public readonly array $args
    ) {
        parent::__construct($site);
    }
}
