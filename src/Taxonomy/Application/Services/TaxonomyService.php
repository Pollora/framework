<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Application\Services;

use Pollora\Entity\Taxonomy;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;

/**
 * Service for managing taxonomies in the application layer.
 * 
 * This service provides methods for working with taxonomies,
 * following hexagonal architecture by using the domain contracts.
 */
class TaxonomyService
{
    /**
     * The taxonomy factory implementation.
     */
    private TaxonomyFactoryInterface $factory;

    /**
     * Create a new TaxonomyService instance.
     */
    public function __construct(TaxonomyFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Create a new taxonomy instance.
     * 
     * @param string $slug The taxonomy slug
     * @param string|array $objectType The post type(s) to be associated
     * @param string|null $singular The singular label for the taxonomy
     * @param string|null $plural The plural label for the taxonomy
     * @return Taxonomy
     */
    public function register(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null): Taxonomy
    {
        return $this->factory->make($slug, $objectType, $singular, $plural);
    }

    /**
     * Check if a taxonomy exists.
     * 
     * @param string $taxonomy The taxonomy slug to check
     * @return bool
     */
    public function exists(string $taxonomy): bool
    {
        return $this->factory->exists($taxonomy);
    }

    /**
     * Get all registered taxonomies.
     * 
     * @return array
     */
    public function getRegistered(): array
    {
        return $this->factory->getRegistered();
    }
} 