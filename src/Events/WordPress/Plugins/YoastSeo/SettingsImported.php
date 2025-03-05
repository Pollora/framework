<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\YoastSeo;

/**
 * Event fired when Yoast SEO settings are imported.
 *
 * This event is triggered when settings are imported from another SEO plugin
 * into Yoast SEO.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class SettingsImported extends YoastSeoEvent
{
    /**
     * Constructor.
     *
     * @param string $source Name of the source plugin
     * @param bool $deleteOldData Whether old data was deleted during import
     */
    public function __construct(
        public readonly string $source,
        public readonly bool $deleteOldData
    ) {
    }
} 