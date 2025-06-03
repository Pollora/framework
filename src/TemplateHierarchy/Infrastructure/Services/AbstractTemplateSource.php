<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Services;

use Pollora\TemplateHierarchy\Domain\Contracts\TemplateSourceInterface;

/**
 * Abstract base class for template sources.
 */
abstract class AbstractTemplateSource implements TemplateSourceInterface
{
    /**
     * The priority of this template source (lower = higher priority).
     */
    protected int $priority = 10;

    /**
     * The name of this template source.
     */
    protected string $name;

    /**
     * Get the priority of this template source.
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the name of this template source.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the priority of this template source.
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }
}
