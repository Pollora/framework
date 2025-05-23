<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Contracts;

/**
 * Interface for registering taxonomies with the underlying system.
 */
interface TaxonomyRegistryInterface
{
    /**
     * Register a taxonomy with the system.
     *
     * @param  object  $taxonomy  The taxonomy to register
     * @return bool True if registration was successful
     */
    public function register(object $taxonomy): bool;

    /**
     * Check if a taxonomy exists.
     *
     * @param  string  $slug  The taxonomy slug to check
     * @return bool True if the taxonomy exists
     */
    public function exists(string $slug): bool;

    /**
     * Get all registered taxonomies.
     *
     * @return array<string, mixed> The registered taxonomies
     */
    public function getAll(): array;
}
