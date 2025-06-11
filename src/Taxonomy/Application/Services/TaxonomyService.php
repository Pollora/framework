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
        return $this->factory->make($slug, $objectType, $singular, $plural);
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
        // Use the factory to create the taxonomy
        $taxonomy = $this->factory->make($slug, $objectType, $singular, $plural);

        // Register the taxonomy with the registry
        $this->registry->register($taxonomy);

        return $taxonomy;
    }

    /**
     * Register a taxonomy instance.
     *
     * @param  object  $taxonomy  The taxonomy instance to register
     */
    public function registerInstance(object $taxonomy): void
    {
        $this->registry->register($taxonomy);
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
}
