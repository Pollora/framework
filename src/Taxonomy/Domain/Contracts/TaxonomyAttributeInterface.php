<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Contracts;

use Pollora\Attributes\Attributable;

/**
 * Interface for Taxonomy classes that can be processed with PHP attributes.
 *
 * Classes implementing this interface can be automatically discovered and registered
 * as WordPress custom taxonomies using PHP 8 attributes for configuration.
 *
 * @property array<string, mixed> $attributeArgs Accumulator for attribute-defined registration arguments
 */
interface TaxonomyAttributeInterface extends Attributable
{
    /**
     * Get the taxonomy slug.
     *
     * @return string The taxonomy slug used for registration
     */
    public function getSlug(): string;

    /**
     * Get the singular name of the taxonomy.
     *
     * @return string The singular name
     */
    public function getName(): string;

    /**
     * Get the plural name of the taxonomy.
     *
     * @return string The plural name
     */
    public function getPluralName(): string;

    /**
     * Get additional arguments to merge with attribute-defined arguments.
     *
     * @return array<string, mixed> Additional arguments
     */
    public function withArgs(): array;

    /**
     * Set a single attribute argument.
     */
    public function setArg(string $key, mixed $value): void;

    /**
     * Get a single attribute argument.
     */
    public function getArg(string $key, mixed $default = null): mixed;
}
