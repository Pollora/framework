<?php

declare(strict_types=1);

namespace Pollora\Meta\Domain\Models;

use Pollora\Meta\Domain\Enums\MetaObjectType;

/**
 * Every typed meta one class declares, with the object they are attached to.
 */
final readonly class MetaSchema
{
    /**
     * @param  class-string  $declaringClass  The `#[PostType]` or `#[Taxonomy]` class
     * @param  MetaObjectType  $objectType  The WordPress object the meta belong to
     * @param  string  $subtype  The post type or taxonomy slug
     * @param  array<string, MetaDefinition>  $definitions  Definitions keyed by property name
     */
    public function __construct(
        public string $declaringClass,
        public MetaObjectType $objectType,
        public string $subtype,
        public array $definitions,
    ) {}

    public function isEmpty(): bool
    {
        return $this->definitions === [];
    }

    /**
     * The definition of a meta, found by its property name or its key.
     */
    public function find(string $propertyOrKey): ?MetaDefinition
    {
        if (isset($this->definitions[$propertyOrKey])) {
            return $this->definitions[$propertyOrKey];
        }

        foreach ($this->definitions as $definition) {
            if ($definition->key === $propertyOrKey) {
                return $definition;
            }
        }

        return null;
    }
}
