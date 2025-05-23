<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Models;

/**
 * Taxonomy domain entity.
 *
 * This class represents a WordPress taxonomy as a domain entity,
 * following the hexagonal architecture principles.
 */
class Taxonomy
{
    /**
     * Create a new Taxonomy instance.
     *
     * @param  string  $slug  The taxonomy slug
     * @param  array<string>|string  $objectTypes  The post types this taxonomy applies to
     * @param  array<string, string>  $labels  The taxonomy labels
     * @param  array<string, mixed>  $args  Additional arguments
     */
    public function __construct(
        private readonly string $slug,
        private readonly array|string $objectTypes,
        private readonly array $labels = [],
        private readonly array $args = []
    ) {}

    /**
     * Get the taxonomy slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the post types this taxonomy is associated with.
     *
     * @return array<string>|string
     */
    public function getObjectTypes(): array|string
    {
        return $this->objectTypes;
    }

    /**
     * Get the taxonomy labels.
     *
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Get the taxonomy arguments.
     *
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
