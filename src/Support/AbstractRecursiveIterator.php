<?php

declare(strict_types=1);

namespace Pollora\Support;

use Illuminate\Support\Collection;
use RecursiveIterator;

abstract class AbstractRecursiveIterator implements RecursiveIterator
{
    protected int $current = 0;

    protected Collection $items;

    public function __construct(Collection|array $items)
    {
        $this->items = $items instanceof Collection ? $items : collect($items);
    }

    public function current(): mixed
    {
        return $this->items[$this->current];
    }

    public function next(): void
    {
        $this->current++;
    }

    public function key(): int
    {
        return $this->current;
    }

    public function valid(): bool
    {
        return $this->items->has($this->current);
    }

    public function rewind(): void
    {
        $this->current = 0;
    }

    abstract public function hasChildren(): bool;

    abstract public function getChildren(): ?self;
}
