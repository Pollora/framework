<?php

declare(strict_types=1);

/**
 * Class PostTypeFactory
 *
 * This class is responsible for creating instances of the PostType class.
 */

namespace Pollen\PostType;

class PostTypeFactory
{
    public function make(string $slug, ?string $singular, ?string $plural)
    {
        return new PostType($slug, $singular, $plural);
    }
}
