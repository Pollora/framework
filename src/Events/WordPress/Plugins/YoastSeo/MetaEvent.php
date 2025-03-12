<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\YoastSeo;

/**
 * Base class for all Yoast SEO meta related events.
 *
 * This abstract class provides the foundation for all meta events,
 * containing the object ID, meta key and meta value that triggered the event.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class MetaEvent extends YoastSeoEvent
{
    /**
     * Constructor.
     *
     * @param  int  $objectId  ID of the object the metadata is for
     * @param  string  $metaKey  Metadata key
     * @param  mixed  $metaValue  Metadata value
     */
    public function __construct(
        public readonly int $objectId,
        public readonly string $metaKey,
        public readonly mixed $metaValue
    ) {}
}
