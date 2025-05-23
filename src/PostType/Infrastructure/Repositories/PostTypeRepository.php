<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Repositories;

use Pollora\PostType\Domain\Contracts\PostTypeRegistryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRepositoryInterface;

/**
 * WordPress implementation of the PostType repository.
 *
 * This repository provides a centralized way to manage post types,
 * abstracting the underlying WordPress registration system while
 * maintaining clean separation of concerns.
 */
class PostTypeRepository implements PostTypeRepositoryInterface
{
    /**
     * In-memory cache of post types for performance.
     *
     * @var array<string, object>
     */
    private array $postTypes = [];

    /**
     * Create a new PostTypeRepository instance.
     */
    public function __construct(
        private readonly PostTypeRegistryInterface $registry
    ) {}

    /**
     * Save a post type to the repository.
     *
     * @param  object  $postType  The post type to save
     * @return bool True if the post type was saved successfully
     */
    public function save(object $postType): bool
    {
        $slug = method_exists($postType, 'getSlug') ? $postType->getSlug() : '';

        if (empty($slug)) {
            return false;
        }

        // Save to in-memory cache
        $this->postTypes[$slug] = $postType;

        // Register with WordPress through the registry
        return $this->registry->register($postType);
    }

    /**
     * Find a post type by its slug.
     *
     * @param  string  $slug  The post type slug
     * @return object|null The post type if found, null otherwise
     */
    public function findBySlug(string $slug): ?object
    {
        // Check in-memory cache first
        if (isset($this->postTypes[$slug])) {
            return $this->postTypes[$slug];
        }

        // Check if it exists in WordPress registry
        if ($this->registry->exists($slug)) {
            // For now, we can't reconstruct the full object from WordPress
            // This would require additional metadata storage
            return null;
        }

        return null;
    }

    /**
     * Check if a post type exists by its slug.
     *
     * @param  string  $slug  The post type slug to check
     * @return bool True if the post type exists
     */
    public function exists(string $slug): bool
    {
        // Check in-memory cache first
        if (isset($this->postTypes[$slug])) {
            return true;
        }

        // Check WordPress registry
        return $this->registry->exists($slug);
    }

    /**
     * Get all post types from the repository.
     *
     * @return array<string, object> Array of post types indexed by slug
     */
    public function findAll(): array
    {
        // Return in-memory cache
        // Note: This doesn't include post types registered outside this repository
        return $this->postTypes;
    }

    /**
     * Remove a post type from the repository.
     *
     * @param  string  $slug  The post type slug to remove
     * @return bool True if the post type was removed successfully
     */
    public function remove(string $slug): bool
    {
        // Remove from in-memory cache
        if (isset($this->postTypes[$slug])) {
            unset($this->postTypes[$slug]);
        }

        // Note: WordPress doesn't provide a built-in way to unregister post types
        // This would require additional implementation
        return true;
    }

    /**
     * Count the number of post types in the repository.
     *
     * @return int The number of post types
     */
    public function count(): int
    {
        return count($this->postTypes);
    }

    /**
     * Clear the in-memory cache.
     */
    public function clearCache(): void
    {
        $this->postTypes = [];
    }

    /**
     * Get all registered WordPress post types.
     *
     * @return array<string, mixed> The registered post types from WordPress
     */
    public function getWordPressPostTypes(): array
    {
        return $this->registry->getAll();
    }
}
