<?php

declare(strict_types=1);

namespace Pollora\PostType\Domain\Contracts;

/**
 * Interface for creating post type instances.
 */
interface PostTypeFactoryInterface
{
    /**
     * Create a new post type instance.
     *
     * @param  string  $slug  The post type slug
     * @param  string|null  $singular  The singular label for the post type
     * @param  string|null  $plural  The plural label for the post type
     * @param  array<string, mixed>  $args  Additional arguments
     */
    public function make(string $slug, ?string $singular = null, ?string $plural = null, array $args = []): object;
}
