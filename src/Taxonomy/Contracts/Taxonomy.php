<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Contracts;

use Pollora\Attributes\Attributable;

/**
 * Interface for taxonomy classes.
 *
 * This interface defines the contract for taxonomy classes that can be
 * configured using attributes.
 */
interface Taxonomy extends Attributable
{
    /**
     * Get the slug for the taxonomy.
     */
    public function getSlug(): string;

    /**
     * Get the singular name of the taxonomy.
     */
    public function getName(): string;

    /**
     * Get the plural name of the taxonomy.
     */
    public function getPluralName(): string;

    /**
     * Get the post types this taxonomy is associated with.
     *
     * @return array<string>|string
     */
    public function getObjectType(): array|string;

    /**
     * Get the arguments for registering the taxonomy.
     *
     * @return array<string, mixed>
     */
    public function getArgs(): array;
}
