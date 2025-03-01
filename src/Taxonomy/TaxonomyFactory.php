<?php

declare(strict_types=1);

/**
 * Class TaxonomyFactory
 *
 * The TaxonomyFactory class is responsible for creating instances of the Taxonomy class.
 */

namespace Pollora\Taxonomy;

use Pollora\Entity\TaxonomyException;
use Illuminate\Foundation\Application;

class TaxonomyFactory
{
    public function __construct(protected Application $container) {}

    /**
     * Creates a new taxonomy.
     *
     * @param  string  $slug  The slug for the taxonomy.
     * @param  string|array  $objectType  The object type(s) associated with the taxonomy.
     * @param  string|null  $singular  The singular name for the taxonomy (optional).
     * @param  string|null  $plural  The plural name for the taxonomy (optional).
     * @return Taxonomy The newly created Taxonomy object.
     *
     * @throws TaxonomyException If the taxonomy with the given slug already exists.
     */
    public function make(string $slug, string|array $objectType, ?string $singular, ?string $plural): \Pollora\Taxonomy\Taxonomy
    {
        $abstract = sprintf('wp.taxonomy.%s', $slug);

        if ($this->container->bound($abstract)) {
            throw new TaxonomyException(sprintf('The taxonomy "%s" already exists.', $slug));
        }

        $taxonomy = new Taxonomy($slug, $objectType, $singular, $plural);
        $this->container->instance($abstract, $taxonomy);

        return $taxonomy;
    }
}
