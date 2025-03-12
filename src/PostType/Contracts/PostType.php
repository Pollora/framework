<?php

declare(strict_types=1);

namespace Pollora\PostType\Contracts;

use Pollora\Attributes\Attributable;

/**
 * Interface for Post Type classes that can be processed with PHP attributes.
 *
 * Classes implementing this interface can be automatically discovered and registered
 * as WordPress custom post types using PHP 8 attributes for configuration.
 */
interface PostType extends Attributable
{
    /**
     * Get the post type slug.
     *
     * @return string The post type slug used for registration
     */
    public function getSlug(): string;

    /**
     * Get the singular name of the post type.
     *
     * @return string The singular name
     */
    public function getName(): string;

    /**
     * Get the plural name of the post type.
     *
     * @return string The plural name
     */
    public function getPluralName(): string;

    /**
     * Get additional arguments to merge with attribute-defined arguments.
     *
     * @return array<string, mixed> Additional arguments
     */
    public function withArgs(): array;
}
