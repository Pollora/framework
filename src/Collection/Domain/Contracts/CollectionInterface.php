<?php

declare(strict_types=1);

namespace Pollora\Collection\Domain\Contracts;

/**
 * Generic collection interface without framework dependencies.
 *
 * Defines basic collection operations that any collection implementation should support.
 */
interface CollectionInterface extends \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Get all items in the collection.
     */
    public function all(): array;

    /**
     * Map over each item in the collection.
     *
     * @return static
     */
    public function map(callable $callback): self;

    /**
     * Filter items in the collection.
     *
     * @return static
     */
    public function filter(?callable $callback = null): self;

    /**
     * Get the values from a single column in the collection.
     *
     * @return static
     */
    public function values(): self;

    /**
     * Determine if the collection is empty.
     */
    public function isEmpty(): bool;

    /**
     * Merge the collection with the given items.
     *
     * @return static
     */
    public function merge(array $items): self;
}
