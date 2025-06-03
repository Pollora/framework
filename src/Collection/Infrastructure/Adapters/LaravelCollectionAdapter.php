<?php

declare(strict_types=1);

namespace Pollora\Collection\Infrastructure\Adapters;

use Illuminate\Support\Collection as LaravelCollection;
use Pollora\Collection\Domain\Contracts\CollectionInterface;

/**
 * Adapter for Laravel Collection to fit our domain CollectionInterface.
 *
 * This is a decorator around Laravel's Collection that implements
 * our framework-agnostic CollectionInterface.
 */
class LaravelCollectionAdapter implements CollectionInterface
{
    private LaravelCollection $laravelCollection;

    /**
     * Create a new LaravelCollectionAdapter instance.
     */
    public function __construct(LaravelCollection|array $items = [])
    {
        $this->laravelCollection = $items instanceof LaravelCollection
            ? $items
            : new LaravelCollection($items);
    }

    /**
     * Get all items in the collection.
     */
    public function all(): array
    {
        return $this->laravelCollection->all();
    }

    /**
     * Map over each item in the collection.
     */
    public function map(callable $callback): CollectionInterface
    {
        return new self($this->laravelCollection->map($callback));
    }

    /**
     * Filter items in the collection.
     */
    public function filter(?callable $callback = null): CollectionInterface
    {
        return new self($this->laravelCollection->filter($callback));
    }

    /**
     * Get the values from a single column in the collection.
     */
    public function values(): CollectionInterface
    {
        return new self($this->laravelCollection->values());
    }

    /**
     * Determine if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return $this->laravelCollection->isEmpty();
    }

    /**
     * Merge the collection with the given items.
     */
    public function merge(array $items): CollectionInterface
    {
        return new self($this->laravelCollection->merge($items));
    }

    /**
     * Get the underlying Laravel collection.
     */
    public function getLaravelCollection(): LaravelCollection
    {
        return $this->laravelCollection;
    }

    /**
     * Get an iterator for the items.
     */
    public function getIterator(): \Traversable
    {
        return $this->laravelCollection->getIterator();
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return $this->laravelCollection->count();
    }

    /**
     * Determine if an item exists at an offset.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->laravelCollection->offsetExists($offset);
    }

    /**
     * Get an item at a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->laravelCollection->offsetGet($offset);
    }

    /**
     * Set the item at a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->laravelCollection->offsetSet($offset, $value);
    }

    /**
     * Unset the item at a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->laravelCollection->offsetUnset($offset);
    }
}
