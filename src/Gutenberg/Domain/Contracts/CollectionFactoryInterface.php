<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Domain\Contracts;

use Illuminate\Support\Collection;

/**
 * Interface for collection factory abstraction.
 *
 * Allows creation of collection objects without framework dependency.
 */
interface CollectionFactoryInterface
{
    /**
     * Create a collection instance from items.
     */
    public function make(array $items): Collection;
}
