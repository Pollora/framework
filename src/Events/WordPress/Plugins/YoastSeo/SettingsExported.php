<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\YoastSeo;

/**
 * Event fired when Yoast SEO settings are exported.
 *
 * This event is triggered when Yoast SEO settings are exported to a file.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class SettingsExported extends YoastSeoEvent
{
    /**
     * Constructor.
     *
     * @param bool $includeTaxonomyMeta Whether taxonomy meta was included in the export
     */
    public function __construct(
        public readonly bool $includeTaxonomyMeta
    ) {
    }
} 