<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Contracts;

/**
 * Interface for Taxonomy repository operations.
 *
 * This interface defines the contract for persisting and retrieving taxonomies.
 * It follows the Repository pattern to abstract data access and provide
 * a clean separation between business logic and data persistence.
 */
interface TaxonomyRepositoryInterface
{
    /**
     * Save a taxonomy to the repository.
     *
     * @param  object  $taxonomy  The taxonomy to save
     * @return bool True if the taxonomy was saved successfully
     */
    public function save(object $taxonomy): bool;

    /**
     * Find a taxonomy by its slug.
     *
     * @param  string  $slug  The taxonomy slug
     * @return object|null The taxonomy if found, null otherwise
     */
    public function findBySlug(string $slug): ?object;

    /**
     * Check if a taxonomy exists by its slug.
     *
     * @param  string  $slug  The taxonomy slug to check
     * @return bool True if the taxonomy exists
     */
    public function exists(string $slug): bool;

    /**
     * Get all taxonomies from the repository.
     *
     * @return array<string, object> Array of taxonomies indexed by slug
     */
    public function findAll(): array;

    /**
     * Remove a taxonomy from the repository.
     *
     * @param  string  $slug  The taxonomy slug to remove
     * @return bool True if the taxonomy was removed successfully
     */
    public function remove(string $slug): bool;

    /**
     * Count the number of taxonomies in the repository.
     *
     * @return int The number of taxonomies
     */
    public function count(): int;
}
