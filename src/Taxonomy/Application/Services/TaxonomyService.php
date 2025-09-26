<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Application\Services;

use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyRegistryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyServiceInterface;

/**
 * Application service for taxonomy management.
 *
 * This service orchestrates the creation and registration of taxonomies
 * following hexagonal architecture principles and implementing the common interface.
 */
readonly class TaxonomyService implements TaxonomyServiceInterface
{
    /**
     * Create a new TaxonomyService instance.
     */
    public function __construct(
        private TaxonomyFactoryInterface $factory,
        private TaxonomyRegistryInterface $registry
    ) {}

    /**
     * Create a new taxonomy instance.
     *
     * @param  string  $slug  The taxonomy slug
     * @param  string|array  $objectType  The post type(s) to be associated
     * @param  string|null  $singular  The singular label for the taxonomy
     * @param  string|null  $plural  The plural label for the taxonomy
     * @param  array<string, mixed>  $args  Additional arguments
     * @return object The created taxonomy instance
     */
    public function create(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null, array $args = []): object
    {
        return $this->factory->make($slug, $objectType, $singular, $plural, $args);
    }

    /**
     * Register a taxonomy with the system.
     *
     * @param  string  $slug  The taxonomy slug
     * @param  string|array  $objectType  The post type(s) to be associated
     * @param  string|null  $singular  The singular label for the taxonomy
     * @param  string|null  $plural  The plural label for the taxonomy
     * @param  array<string, mixed>  $args  Additional arguments
     * @return object The registered taxonomy instance
     */
    public function register(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null, array $args = []): object
    {
        // The factory creates and registers the taxonomy (pollora/entity handles register_taxonomy)
        return $this->factory->make($slug, $objectType, $singular, $plural, $args);
    }

    /**
     * Check if a taxonomy exists.
     *
     * @param  string  $slug  The taxonomy slug to check
     * @return bool True if the taxonomy exists
     */
    public function exists(string $slug): bool
    {
        return $this->registry->exists($slug);
    }

    /**
     * Get all registered taxonomies.
     *
     * @return array<string, mixed> The registered taxonomies
     */
    public function getRegistered(): array
    {
        return $this->registry->getAll();
    }

    /**
     * Register a taxonomy from a class with Taxonomy attribute.
     *
     * @param  string  $className  The fully qualified class name
     * @return object|null The registered taxonomy instance or null if failed
     */
    public function registerFromClass(string $className): ?object
    {
        try {
            // Check if class exists
            if (! class_exists($className)) {
                return null;
            }

            $reflection = new \ReflectionClass($className);

            // Find Taxonomy attribute
            $taxonomyAttributes = $reflection->getAttributes(\Pollora\Attributes\Taxonomy::class);

            if ($taxonomyAttributes === []) {
                return null;
            }

            // Get the first Taxonomy attribute instance
            $taxonomyAttribute = $taxonomyAttributes[0]->newInstance();

            // Extract data from the attribute
            $slug = $taxonomyAttribute->getSlug($className);
            $objectType = $taxonomyAttribute->getObjectType() ?? ['post'];
            $singular = $taxonomyAttribute->getSingular($className);
            $plural = $taxonomyAttribute->getPlural($className);

            // Register the taxonomy
            return $this->register($slug, $objectType, $singular, $plural);

        } catch (\Throwable) {
            // Return null on any error
            return null;
        }
    }
}
