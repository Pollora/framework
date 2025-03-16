<?php

declare(strict_types=1);

namespace Pollora\Attributes\PostType;

use Attribute;
use Pollora\PostType\Contracts\PostType;

/**
 * Attribute to set the map_meta_cap parameter for a post type.
 *
 * When set to true, WordPress will map meta capabilities to primitive capabilities.
 * This is usually used together with the CapabilityType attribute.
 *
 * @Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class MapMetaCap extends PostTypeAttribute
{
    /**
     * Constructor.
     *
     * @param  bool  $value  Whether to map meta capabilities
     */
    public function __construct(
        private readonly bool $value = true
    ) {}

    /**
     * Configure the post type with the map_meta_cap parameter.
     *
     * @param  PostType  $postType  The post type to configure
     */
    protected function configure(PostType $postType): void
    {
        $postType->attributeArgs['map_meta_cap'] = $this->value;
    }
}
