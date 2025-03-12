<?php

declare(strict_types=1);

namespace Pollora\Attributes\PostType;

use Attribute;
use Pollora\PostType\Contracts\PostType;

/**
 * Attribute to set the capabilities parameter for a post type.
 *
 * Provides an array of capabilities for this post type.
 * See WordPress documentation for the full list of capabilities.
 *
 * @Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Capabilities extends PostTypeAttribute
{
    /**
     * Constructor.
     *
     * @param  array  $value  The capabilities array for the post type
     */
    public function __construct(
        private array $value
    ) {}

    /**
     * Configure the post type with the capabilities parameter.
     *
     * @param  PostType  $postType  The post type to configure
     */
    protected function configure(PostType $postType): void
    {
        $postType->attributeArgs['capabilities'] = $this->value;
    }
}
