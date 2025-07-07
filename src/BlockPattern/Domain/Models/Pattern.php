<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Models;

/**
 * Domain model representing a block pattern.
 *
 * This is a pure domain object with no framework dependencies.
 */
class Pattern
{
    /**
     * Create a new pattern.
     *
     * @param  string  $slug  Unique identifier for the pattern
     * @param  string  $title  Display title for the pattern
     * @param  string  $content  Pattern content (HTML/markup)
     * @param  string|null  $description  Optional description
     * @param  array<int, string>|null  $categories  Optional categories this pattern belongs to
     * @param  array<int, string>|null  $keywords  Optional keywords for searching
     * @param  array<int, string>|null  $blockTypes  Optional block types this pattern is for
     * @param  array<int, string>|null  $postTypes  Optional post types this pattern is for
     * @param  bool|null  $inserter  Whether to show in inserter
     * @param  int|null  $viewportWidth  Optional viewport width
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $title,
        private readonly string $content,
        private readonly ?string $description = null,
        private readonly ?array $categories = null,
        private readonly ?array $keywords = null,
        private readonly ?array $blockTypes = null,
        private readonly ?array $postTypes = null,
        private readonly ?bool $inserter = null,
        private readonly ?int $viewportWidth = null
    ) {}

    /**
     * Get the pattern slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the pattern title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the pattern content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the pattern description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the pattern categories.
     *
     * @return array<int, string>|null
     */
    public function getCategories(): ?array
    {
        return $this->categories;
    }

    /**
     * Get the pattern keywords.
     *
     * @return array<int, string>|null
     */
    public function getKeywords(): ?array
    {
        return $this->keywords;
    }

    /**
     * Get the pattern block types.
     *
     * @return array<int, string>|null
     */
    public function getBlockTypes(): ?array
    {
        return $this->blockTypes;
    }

    /**
     * Get the pattern post types.
     *
     * @return array<int, string>|null
     */
    public function getPostTypes(): ?array
    {
        return $this->postTypes;
    }

    /**
     * Whether the pattern should be shown in the inserter.
     */
    public function getInserter(): ?bool
    {
        return $this->inserter;
    }

    /**
     * Get the pattern viewport width.
     */
    public function getViewportWidth(): ?int
    {
        return $this->viewportWidth;
    }

    /**
     * Convert the domain object to an array representation for registration.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->categories !== null) {
            $data['categories'] = $this->categories;
        }

        if ($this->keywords !== null) {
            $data['keywords'] = $this->keywords;
        }

        if ($this->blockTypes !== null) {
            $data['blockTypes'] = $this->blockTypes;
        }

        if ($this->postTypes !== null) {
            $data['postTypes'] = $this->postTypes;
        }

        if ($this->inserter !== null) {
            $data['inserter'] = $this->inserter;
        }

        if ($this->viewportWidth !== null) {
            $data['viewportWidth'] = $this->viewportWidth;
        }

        return $data;
    }

    /**
     * Create a pattern from raw data.
     *
     * @param  array<string, mixed>  $data  Raw pattern data
     * @param  string  $content  Pattern content
     */
    public static function fromArray(array $data, string $content): self
    {
        return new self(
            $data['slug'],
            $data['title'],
            $content,
            $data['description'] ?? null,
            $data['categories'] ?? null,
            $data['keywords'] ?? null,
            $data['blockTypes'] ?? null,
            $data['postTypes'] ?? null,
            $data['inserter'] ?? null,
            $data['viewportWidth'] ?? null
        );
    }
}
