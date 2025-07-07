<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Factories;

use Illuminate\Support\Str;
use Pollora\Entity\Taxonomy as EntityTaxonomy;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;

/**
 * Implementation of the TaxonomyFactory interface.
 *
 * This factory creates Taxonomy instances from attributes or configuration arrays,
 * ensuring consistency across the framework while maintaining the
 * hexagonal architecture principles.
 */
class TaxonomyFactory implements TaxonomyFactoryInterface
{
    /**
     * Create a new taxonomy instance.
     *
     * @param  string  $slug  The taxonomy slug
     * @param  string|array  $objectType  The post type(s) to be associated
     * @param  string|null  $singular  The singular label for the taxonomy
     * @param  string|null  $plural  The plural label for the taxonomy
     * @param  array<string, mixed>  $args  Additional arguments
     */
    public function make(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null, array $args = []): mixed
    {
        // Generate singular name if not provided
        if ($singular === null) {
            $singular = $this->generateSingularName($slug);
        }

        // Generate plural name if not provided
        if ($plural === null) {
            $plural = $this->generatePluralName($singular);
        }

        // Create the EntityTaxonomy instance
        $taxonomy = EntityTaxonomy::make($slug, $objectType, $singular, $plural);

        // Apply additional arguments if provided
        if ($args !== []) {
            $taxonomy->setRawArgs($args);
        }

        return $taxonomy;
    }

    /**
     * Generate a singular name from a slug.
     *
     * @param  string  $slug  The post type slug
     * @return string The generated singular name
     */
    private function generateSingularName(string $slug): string
    {
        // Convert to snake_case first
        $snakeCase = Str::snake($slug);

        // Then humanize it (convert snake_case to words with spaces and capitalize first letter)
        $humanized = ucfirst(str_replace('_', ' ', $snakeCase));

        // Ensure it's singular
        return Str::singular($humanized);
    }

    /**
     * Generate a plural name from a singular name.
     *
     * @param  string  $singular  The singular name
     * @return string The generated plural name
     */
    private function generatePluralName(string $singular): string
    {
        return Str::plural($singular);
    }
}
