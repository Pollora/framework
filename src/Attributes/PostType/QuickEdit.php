<?php

declare(strict_types=1);

namespace Pollora\Attributes\PostType;

use Attribute;
use Pollora\PostType\Contracts\PostType;

/**
 * Attribute to set the quick_edit parameter for a post type.
 *
 * When set to true, the quick edit functionality will be enabled for this post type
 * in the WordPress admin list table.
 * When set to false, the quick edit functionality will be disabled.
 *
 * @Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class QuickEdit extends PostTypeAttribute
{
    /**
     * Constructor.
     *
     * @param bool $value Whether to enable quick edit for this post type
     */
    public function __construct(
        private bool $value = true
    ) {
    }

    /**
     * Configure the post type with the quick_edit parameter.
     *
     * @param PostType $postType The post type to configure
     *
     * @return void
     */
    protected function configure(PostType $postType): void
    {
        $postType->attributeArgs['quick_edit'] = $this->value;
    }
} 