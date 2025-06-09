<?php

declare(strict_types=1);

namespace Pollora\PostType\Domain\Models;

/**
 * PostType domain entity.
 *
 * This class represents a WordPress post type as a domain entity,
 * following the hexagonal architecture principles.
 */
class PostType
{
    /**
     * Create a new PostType instance.
     *
     * @param  string  $slug  The post type slug
     * @param  string|null  $singular  The singular label
     * @param  string|null  $plural  The plural label
     * @param  array<string, mixed>  $args  Additional arguments
     */
    public function __construct(
        private readonly string $slug,
        private readonly ?string $singular = null,
        private readonly ?string $plural = null,
        private readonly array $args = []
    ) {}

    /**
     * Get the post type slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the singular name.
     */
    public function getSingularName(): ?string
    {
        return $this->singular;
    }

    /**
     * Get the plural name.
     */
    public function getPluralName(): ?string
    {
        return $this->plural;
    }

    /**
     * Get the post type arguments.
     *
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Create a new instance with updated arguments.
     *
     * @param  array<string, mixed>  $args
     */
    public function withArgs(array $args): self
    {
        return new self(
            $this->slug,
            $this->singular,
            $this->plural,
            array_merge($this->args, $args)
        );
    }
}
