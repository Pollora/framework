<?php

declare(strict_types=1);

namespace Pollora\Attributes\PostType;

use Attribute;
use Pollora\PostType\Domain\Contracts\PostTypeAttributeInterface;

/**
 * Attribute to set the exclude_from_search parameter for a post type.
 *
 * When set to true, the post type will be excluded from search results.
 * When set to false, the post type will be included in search results.
 *
 * @Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ExcludeFromSearch extends PostTypeAttribute
{
    /**
     * Constructor.
     *
     * @param  bool  $value  Whether the post type should be excluded from search results
     */
    public function __construct(
        public readonly bool $value = true
    ) {}

    /**
     * Configure the post type with the exclude_from_search parameter.
     *
     * @param  PostTypeAttributeInterface  $postType  The post type to configure
     */
    protected function configure(PostTypeAttributeInterface $postType): void
    {
        $postType->attributeArgs['exclude_from_search'] = $this->value;
    }
}
