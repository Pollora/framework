<?php

declare(strict_types=1);

namespace Pollora\Support;

use Illuminate\Support\Collection;
use RecursiveIterator;

/**
 * Abstract base class for recursive iteration implementation.
 *
 * This class provides a foundation for implementing recursive iterators,
 * with support for Laravel Collections and arrays.
 *
 * @implements \RecursiveIterator<int, mixed>
 */
abstract class AbstractRecursiveIterator implements RecursiveIterator
{
    /**
     * Current position in the iterator.
     */
    protected int $current = 0;

    /**
     * Collection of items to iterate over.
     */
    protected Collection $items;

    /**
     * Create a new recursive iterator instance.
     *
     * @param  Collection|array  $items  Items to iterate over
     */
    public function __construct(Collection|array $items)
    {
        $this->items = $items instanceof Collection ? $items : collect($items);
    }

    /**
     * Get the current item.
     *
     * @return mixed The current item
     */
    public function current(): mixed
    {
        return $this->items[$this->current];
    }

    /**
     * Move to the next item.
     */
    public function next(): void
    {
        $this->current++;
    }

    /**
     * Get the current position.
     *
     * @return int Current iterator position
     */
    public function key(): int
    {
        return $this->current;
    }

    /**
     * Check if the current position is valid.
     *
     * @return bool True if the current position is valid
     */
    public function valid(): bool
    {
        return $this->items->has($this->current);
    }

    /**
     * Reset the iterator to the beginning.
     */
    public function rewind(): void
    {
        $this->current = 0;
    }

    /**
     * Check if the current item has children.
     *
     * @return bool True if the current item has children
     */
    abstract public function hasChildren(): bool;

    /**
     * Get the child iterator for the current item.
     *
     * @return self|null Child iterator or null if no children
     */
    abstract public function getChildren(): ?self;
}
