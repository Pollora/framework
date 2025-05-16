<?php

declare(strict_types=1);

/**
 * Class TaxonomyFactory
 *
 * The TaxonomyFactory class is responsible for creating instances of the Taxonomy class.
 */

namespace Pollora\Taxonomy;

use Pollora\Entity\Taxonomy;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;

class TaxonomyFactory implements TaxonomyFactoryInterface
{
    /**
     * Creates a new taxonomy.
     *
     * @param  string  $slug  The slug for the taxonomy.
     * @param  string|array  $objectType  The object type(s) associated with the taxonomy.
     * @param  string|null  $singular  The singular name for the taxonomy (optional).
     * @param  string|null  $plural  The plural name for the taxonomy (optional).
     * @return Taxonomy The newly created Taxonomy object.
     */
    public function make(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null): Taxonomy
    {
        return new Taxonomy($slug, $objectType, $singular, $plural);
    }

    /**
     * Check if a taxonomy exists.
*
     * @param string $taxonomy The taxonomy slug to check
     * @return bool
     */
    public function exists(string $taxonomy): bool
    {
        // Implementation would depend on WordPress functions or your own abstraction
        // This is a placeholder for the actual implementation
        // In a WordPress context, this might use taxonomy_exists()
        return false;
    }

    /**
     * Get all registered taxonomies.
     *
     * @return array
     */
    public function getRegistered(): array
    {
        // Implementation would depend on WordPress functions or your own abstraction
        // This is a placeholder for the actual implementation
        // In a WordPress context, this might use get_taxonomies()
        return [];
    }
}
