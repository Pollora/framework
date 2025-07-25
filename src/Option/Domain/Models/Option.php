<?php

declare(strict_types=1);

namespace Pollora\Option\Domain\Models;

/**
 * Represents an immutable WordPress option value object.
 *
 * @psalm-immutable
 */
final readonly class Option
{
    public function __construct(
        public string $key,
        public mixed $value,
        public bool $autoload = true
    ) {}

    /**
     * Create a new Option instance with a different value.
     */
    public function withValue(mixed $value): self
    {
        return new self($this->key, $value, $this->autoload);
    }

    /**
     * Create a new Option instance with a different autoload setting.
     */
    public function withAutoload(bool $autoload): self
    {
        return new self($this->key, $this->value, $autoload);
    }
}
