<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

use WP_Site;

/**
 * Base class for all blog-related events.
 *
 * This abstract class provides the foundation for all blog events,
 * containing the site object that triggered the event.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class BlogEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly WP_Site $site
    ) {
    }
} 