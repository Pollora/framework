<?php

declare(strict_types=1);

namespace Pollora\Meta\Application\Services;

use Pollora\Meta\Domain\Exceptions\InvalidMetaDefinitionException;
use Pollora\Meta\Domain\Models\MetaDefinition;
use Pollora\Meta\Domain\Models\MetaSchema;

/**
 * The meta schemas discovered in the project, by declaring class.
 */
final class MetaSchemaRepository
{
    /**
     * @var array<class-string, MetaSchema>
     */
    private array $schemas = [];

    /**
     * @throws InvalidMetaDefinitionException When another class already declares one of its keys on the same object
     */
    public function add(MetaSchema $schema): void
    {
        foreach ($this->schemas as $existing) {
            if ($existing->declaringClass === $schema->declaringClass || ! $existing->overlaps($schema)) {
                continue;
            }

            foreach ($schema->definitions as $definition) {
                if ($existing->find($definition->key) instanceof MetaDefinition) {
                    throw InvalidMetaDefinitionException::duplicateKey($definition->key, $schema->ownerName(), $existing->declaringClass, $schema->declaringClass);
                }
            }
        }

        $this->schemas[$schema->declaringClass] = $schema;
    }

    /**
     * @param  class-string  $class
     */
    public function forClass(string $class): ?MetaSchema
    {
        return $this->schemas[$class] ?? null;
    }

    /**
     * @return list<MetaSchema>
     */
    public function all(): array
    {
        return array_values($this->schemas);
    }
}
