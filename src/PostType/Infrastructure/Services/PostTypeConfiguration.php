<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Services;

use Pollora\PostType\Domain\Contracts\PostTypeAttributeInterface;

/**
 * PostType Configuration Builder
 *
 * This class manages the configuration for a WordPress custom post type during
 * the discovery and registration process. It provides methods to set and get
 * configuration values while building the complete post type registration.
 */
class PostTypeConfiguration implements PostTypeAttributeInterface
{
    /**
     * The post type slug.
     */
    private string $slug;

    /**
     * The singular name of the post type.
     */
    private string $singular;

    /**
     * The plural name of the post type.
     */
    private string $plural;

    /**
     * The WordPress registration arguments.
     */
    public array $attributeArgs = [];

    /**
     * Create a new PostType configuration.
     *
     * @param  string  $slug  The post type slug
     * @param  string  $singular  The singular name
     * @param  string  $plural  The plural name
     * @param  array<string, mixed>  $initialArgs  Initial arguments
     */
    public function __construct(string $slug, string $singular, string $plural, array $initialArgs = [])
    {
        $this->slug = $slug;
        $this->singular = $singular;
        $this->plural = $plural;
        $this->attributeArgs = $initialArgs;
    }

    /**
     * Get the post type slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the singular name of the post type.
     */
    public function getName(): string
    {
        return $this->singular;
    }

    /**
     * Get the plural name of the post type.
     */
    public function getPluralName(): string
    {
        return $this->plural;
    }

    /**
     * Get additional arguments to merge with attribute-defined arguments.
     */
    public function withArgs(): array
    {
        return $this->attributeArgs;
    }

    /**
     * Set a specific argument value.
     *
     * @param  string  $key  The argument key
     * @param  mixed  $value  The argument value
     */
    public function setArg(string $key, mixed $value): void
    {
        $this->attributeArgs[$key] = $value;
    }

    /**
     * Get a specific argument value.
     *
     * @param  string  $key  The argument key
     * @param  mixed  $default  The default value if key doesn't exist
     */
    public function getArg(string $key, mixed $default = null): mixed
    {
        return $this->attributeArgs[$key] ?? $default;
    }

    /**
     * Merge additional arguments into the configuration.
     *
     * @param  array<string, mixed>  $args  Arguments to merge
     */
    public function mergeArgs(array $args): void
    {
        $this->attributeArgs = array_merge($this->attributeArgs, $args);
    }

    /**
     * Get all arguments as an array.
     */
    public function getArgs(): array
    {
        return $this->attributeArgs;
    }

    /**
     * Get the complete configuration for post type registration.
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'singular' => $this->singular,
            'plural' => $this->plural,
            'args' => $this->attributeArgs,
        ];
    }
}
