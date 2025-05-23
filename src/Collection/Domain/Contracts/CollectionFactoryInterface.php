<?php

declare(strict_types=1);

namespace Pollora\Collection\Domain\Contracts;

/**
 * Interface for collection factory abstraction.
 *
 * Allows creation of collection objects without framework dependency.
 */
interface CollectionFactoryInterface
{
    /**
     * Create a collection instance from items.
     *
     * @param  array  $items  Items to collect
     * @return CollectionInterface Framework-agnostic collection
     */
    public function make(array $items): CollectionInterface;
}
