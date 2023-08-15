<?php

declare(strict_types=1);

/**
 * Class PostTypeFactory
 *
 * This class is responsible for creating instances of the PostType class.
 */

namespace Pollen\Taxonomy;

class TaxonomyFactory
{
    public function make(string $slug, string|array $objectType, string|null $singular, string|null $plural)
    {
        return new Taxonomy($slug, $objectType, $singular, $plural);
    }
}
