<?php

declare(strict_types=1);

namespace Pollora\BlockCategory\Domain\Models;

/**
 * Domain model representing a Gutenberg block category.
 *
 * This is a pure domain object with no framework dependencies.
 */
readonly class BlockCategory
{
    /**
     * Create a new block category.
     *
     * @param  string  $slug  Unique identifier for the category
     * @param  string  $title  Display name for the category
     */
    public function __construct(
        private string $slug,
        private string $title
    ) {}

    /**
     * Get the category slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the category title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Convert the domain object to an array representation.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
        ];
    }
}
