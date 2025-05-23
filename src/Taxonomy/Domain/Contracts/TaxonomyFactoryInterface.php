<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Contracts;

/**
 * Interface for creating taxonomy instances.
 */
interface TaxonomyFactoryInterface
{
    /**
     * Create a new taxonomy instance.
     *
     * @param  string  $slug  The taxonomy slug
     * @param  string|array  $objectType  The post type(s) to be associated
     * @param  string|null  $singular  The singular label for the taxonomy
     * @param  string|null  $plural  The plural label for the taxonomy
     */
    public function make(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null): mixed;
}
