<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Models;

/**
 * Represents a discovered class in the system.
 */
final readonly class DiscoveredClass
{
    /**
     * @param  string  $className  Fully qualified class name
     * @param  string  $type  Type identifier for the class
     */
    public function __construct(
        private string $className,
        private string $type
    ) {}

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
