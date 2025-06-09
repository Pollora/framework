<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Repositories;

use Pollora\Taxonomy\Domain\Contracts\TaxonomyRegistryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyRepositoryInterface;

/**
 * WordPress implementation of the Taxonomy repository.
 *
 * This repository provides a centralized way to manage taxonomies,
 * abstracting the underlying WordPress registration system while
 * maintaining clean separation of concerns.
 */
class TaxonomyRepository implements TaxonomyRepositoryInterface
{
    /**
     * In-memory cache of taxonomies for performance.
     *
     * @var array<string, object>
     */
    private array $taxonomies = [];

    /**
     * Create a new TaxonomyRepository instance.
     */
    public function __construct(
        private readonly TaxonomyRegistryInterface $registry
    ) {}

    /**
     * Save a taxonomy to the repository.
     *
     * @param  object  $taxonomy  The taxonomy to save
     * @return bool True if the taxonomy was saved successfully
     */
    public function save(object $taxonomy): bool
    {
        $slug = method_exists($taxonomy, 'getSlug') ? $taxonomy->getSlug() : '';

        if (empty($slug)) {
            return false;
        }

        // Save to in-memory cache
        $this->taxonomies[$slug] = $taxonomy;

        // Register with WordPress through the registry
        return $this->registry->register($taxonomy);
    }

    /**
     * Find a taxonomy by its slug.
     *
     * @param  string  $slug  The taxonomy slug
     * @return object|null The taxonomy if found, null otherwise
     */
    public function findBySlug(string $slug): ?object
    {
        // Check in-memory cache first
        if (isset($this->taxonomies[$slug])) {
            return $this->taxonomies[$slug];
        }

        // Check if it exists in WordPress registry
        return null;
    }

    /**
     * Check if a taxonomy exists by its slug.
     *
     * @param  string  $slug  The taxonomy slug to check
     * @return bool True if the taxonomy exists
     */
    public function exists(string $slug): bool
    {
        // Check in-memory cache first
        if (isset($this->taxonomies[$slug])) {
            return true;
        }

        // Check WordPress registry
        return $this->registry->exists($slug);
    }

    /**
     * Get all taxonomies from the repository.
     *
     * @return array<string, object> Array of taxonomies indexed by slug
     */
    public function findAll(): array
    {
        // Return in-memory cache
        // Note: This doesn't include taxonomies registered outside this repository
        return $this->taxonomies;
    }

    /**
     * Remove a taxonomy from the repository.
     *
     * @param  string  $slug  The taxonomy slug to remove
     * @return bool True if the taxonomy was removed successfully
     */
    public function remove(string $slug): bool
    {
        // Remove from in-memory cache
        if (isset($this->taxonomies[$slug])) {
            unset($this->taxonomies[$slug]);
        }

        // Note: WordPress doesn't provide a built-in way to unregister taxonomies
        // This would require additional implementation
        return true;
    }

    /**
     * Count the number of taxonomies in the repository.
     *
     * @return int The number of taxonomies
     */
    public function count(): int
    {
        return count($this->taxonomies);
    }

    /**
     * Clear the in-memory cache.
     */
    public function clearCache(): void
    {
        $this->taxonomies = [];
    }

    /**
     * Get all registered WordPress taxonomies.
     *
     * @return array<string, mixed> The registered taxonomies from WordPress
     */
    public function getWordPressTaxonomies(): array
    {
        return $this->registry->getAll();
    }
}
