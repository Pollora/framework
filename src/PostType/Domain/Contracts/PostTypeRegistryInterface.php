<?php

declare(strict_types=1);

namespace Pollora\PostType\Domain\Contracts;

/**
 * Interface for registering post types with the underlying system.
 */
interface PostTypeRegistryInterface
{
    /**
     * Register a post type with the system.
     *
     * @param  object  $postType  The post type to register
     * @return bool True if registration was successful
     */
    public function register(object $postType): bool;

    /**
     * Check if a post type exists.
     *
     * @param  string  $slug  The post type slug to check
     * @return bool True if the post type exists
     */
    public function exists(string $slug): bool;

    /**
     * Get all registered post types.
     *
     * @return array<string, mixed> The registered post types
     */
    public function getAll(): array;
}
