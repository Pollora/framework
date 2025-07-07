<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Models;

/**
 * Domain model representing a pattern category.
 *
 * This is a pure domain object with no framework dependencies.
 */
class PatternCategory
{
    /**
     * Create a new pattern category.
     *
     * @param  string  $slug  Unique identifier for the category
     * @param  array<string, mixed>  $attributes  Additional attributes for the category
     */
    public function __construct(
        private readonly string $slug,
        private readonly array $attributes = []
    ) {}

    /**
     * Get the category slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the category attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the domain object to an array representation for registration.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'attributes' => $this->attributes,
        ];
    }
}
