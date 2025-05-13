<?php

declare(strict_types=1);

namespace Pollora\PostType\Domain\Contracts;

use Pollora\Entity\PostType;

/**
 * Interface for creating and managing post types.
 *
 * This interface defines the contract for post type factory implementations
 * following the hexagonal architecture pattern.
 */
interface PostTypeFactoryInterface
{
    /**
     * Create a new post type instance.
     *
     * @param string $slug The post type slug
     * @param string|null $singular The singular label for the post type
     * @param string|null $plural The plural label for the post type
     * @return PostType
     */
    public function make(string $slug, ?string $singular = null, ?string $plural = null): PostType;

    /**
     * Check if a post type exists.
     *
     * @param string $postType The post type slug to check
     * @return bool
     */
    public function exists(string $postType): bool;

    /**
     * Get all registered post types.
     *
     * @return array
     */
    public function getRegistered(): array;
} 