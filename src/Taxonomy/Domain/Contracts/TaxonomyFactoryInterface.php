<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Contracts;

use Pollora\Entity\Taxonomy;

/**
 * Interface for creating and managing taxonomies.
 *
 * This interface defines the contract for taxonomy factory implementations
 * following the hexagonal architecture pattern.
 */
interface TaxonomyFactoryInterface
{
    /**
     * Create a new taxonomy instance.
     *
     * @param string $slug The taxonomy slug
     * @param string|array $objectType The post type(s) to be associated
     * @param string|null $singular The singular label for the taxonomy
     * @param string|null $plural The plural label for the taxonomy
     * @return Taxonomy
     */
    public function make(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null): Taxonomy;

    /**
     * Check if a taxonomy exists.
     *
     * @param string $taxonomy The taxonomy slug to check
     * @return bool
     */
    public function exists(string $taxonomy): bool;

    /**
     * Get all registered taxonomies.
     *
     * @return array
     */
    public function getRegistered(): array;
} 