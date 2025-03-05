<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\YoastSeo;

/**
 * Event fired when Yoast SEO files are updated.
 *
 * This event is triggered when robots.txt or .htaccess files are created or updated
 * through Yoast SEO.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class FileUpdated extends YoastSeoEvent
{
    /**
     * Constructor.
     *
     * @param string $action The action performed ('create_robots', 'update_robots', or 'update_htaccess')
     */
    public function __construct(
        public readonly string $action
    ) {
    }
} 