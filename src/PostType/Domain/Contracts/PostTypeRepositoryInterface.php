<?php

declare(strict_types=1);

namespace Pollora\PostType\Domain\Contracts;

/**
 * Interface for PostType repository operations.
 *
 * This interface defines the contract for persisting and retrieving post types.
 * It follows the Repository pattern to abstract data access and provide
 * a clean separation between business logic and data persistence.
 */
interface PostTypeRepositoryInterface
{
    /**
     * Save a post type to the repository.
     *
     * @param  object  $postType  The post type to save
     * @return bool True if the post type was saved successfully
     */
    public function save(object $postType): bool;

    /**
     * Find a post type by its slug.
     *
     * @param  string  $slug  The post type slug
     * @return object|null The post type if found, null otherwise
     */
    public function findBySlug(string $slug): ?object;

    /**
     * Check if a post type exists by its slug.
     *
     * @param  string  $slug  The post type slug to check
     * @return bool True if the post type exists
     */
    public function exists(string $slug): bool;

    /**
     * Get all post types from the repository.
     *
     * @return array<string, object> Array of post types indexed by slug
     */
    public function findAll(): array;

    /**
     * Remove a post type from the repository.
     *
     * @param  string  $slug  The post type slug to remove
     * @return bool True if the post type was removed successfully
     */
    public function remove(string $slug): bool;

    /**
     * Count the number of post types in the repository.
     *
     * @return int The number of post types
     */
    public function count(): int;
}
