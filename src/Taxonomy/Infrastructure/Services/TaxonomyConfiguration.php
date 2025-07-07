<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Services;

use Pollora\Taxonomy\Domain\Contracts\TaxonomyAttributeInterface;

/**
 * Taxonomy Configuration Builder
 *
 * This class manages the configuration for a WordPress custom taxonomy during
 * the discovery and registration process. It provides methods to set and get
 * configuration values while building the complete taxonomy registration.
 */
class TaxonomyConfiguration implements TaxonomyAttributeInterface
{
    /**
     * Create a new Taxonomy configuration.
     *
     * @param  string  $slug  The taxonomy slug
     * @param  string  $singular  The singular name
     * @param  string  $plural  The plural name
     * @param  string|array  $objectType  The post type(s) this taxonomy applies to
     * @param  array<string, mixed>  $attributeArgs  Initial arguments
     */
    public function __construct(private readonly string $slug, private readonly string $singular, private readonly string $plural, private string|array $objectType, public array $attributeArgs = []) {}

    /**
     * Get the taxonomy slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the singular name of the taxonomy.
     */
    public function getName(): string
    {
        return $this->singular;
    }

    /**
     * Get the plural name of the taxonomy.
     */
    public function getPluralName(): string
    {
        return $this->plural;
    }

    /**
     * Get the object type(s) this taxonomy applies to.
     */
    public function getObjectType(): string|array
    {
        return $this->attributeArgs['object_type'] ?? $this->objectType;
    }

    /**
     * Set the object type(s) this taxonomy applies to.
     */
    public function setObjectType(string|array $objectType): void
    {
        $this->objectType = $objectType;
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
     * Get the complete configuration for taxonomy registration.
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'singular' => $this->singular,
            'plural' => $this->plural,
            'object_type' => $this->objectType,
            'args' => $this->attributeArgs,
        ];
    }
}
