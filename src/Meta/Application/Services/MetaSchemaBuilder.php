<?php

declare(strict_types=1);

namespace Pollora\Meta\Application\Services;

use BackedEnum;
use DateTimeInterface;
use Pollora\Attributes\CommentMeta;
use Pollora\Attributes\Meta;
use Pollora\Attributes\PostMeta;
use Pollora\Attributes\PostType;
use Pollora\Attributes\Taxonomy;
use Pollora\Attributes\TermMeta;
use Pollora\Attributes\UserMeta;
use Pollora\Meta\Domain\Enums\MetaObjectType;
use Pollora\Meta\Domain\Enums\MetaValueType;
use Pollora\Meta\Domain\Exceptions\InvalidMetaDefinitionException;
use Pollora\Meta\Domain\Models\MetaDefinition;
use Pollora\Meta\Domain\Models\MetaSchema;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use UnitEnum;

/**
 * Builds the meta schema of a class declaring meta (`#[PostType]`, `#[Taxonomy]`,
 * `#[PostMeta]`, `#[TermMeta]`, `#[UserMeta]`, `#[CommentMeta]`) from its
 * `#[Meta]` properties.
 *
 * Every rule WordPress would otherwise break silently is checked here, so a
 * wrong declaration fails at discovery with the class and property named.
 */
final class MetaSchemaBuilder
{
    /**
     * @param  class-string  $class
     *
     * @throws InvalidMetaDefinitionException When the class is not a declaration or a property cannot be registered
     */
    public function build(string $class): MetaSchema
    {
        $reflection = new ReflectionClass($class);
        [$objectType, $subtypes] = $this->resolveOwner($reflection);

        $definitions = [];
        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $property->getAttributes(Meta::class);

            if ($attributes === [] || $property->isStatic()) {
                continue;
            }

            $definition = $this->buildDefinition($class, $property, $attributes[0]->newInstance(), $objectType);

            if (isset($properties[$definition->key])) {
                throw InvalidMetaDefinitionException::forProperty($class, $property->getName(), sprintf(
                    'the key "%s" is already used by $%s.',
                    $definition->key,
                    $properties[$definition->key]
                ));
            }

            $properties[$definition->key] = $definition->property;
            $definitions[$definition->property] = $definition;
        }

        return new MetaSchema($class, $objectType, $subtypes, $definitions);
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return array{0: MetaObjectType, 1: list<string>}
     */
    private function resolveOwner(ReflectionClass $reflection): array
    {
        $class = $reflection->getName();
        $owner = static fn (string $attribute): ?object => ($reflection->getAttributes($attribute)[0] ?? null)?->newInstance();

        return match (true) {
            ($postType = $owner(PostType::class)) instanceof PostType => [MetaObjectType::Post, [$postType->resolveSlug($class)]],
            ($taxonomy = $owner(Taxonomy::class)) instanceof Taxonomy => [MetaObjectType::Term, [$taxonomy->resolveSlug($class)]],
            ($postMeta = $owner(PostMeta::class)) instanceof PostMeta => [MetaObjectType::Post, $postMeta->postTypes],
            ($termMeta = $owner(TermMeta::class)) instanceof TermMeta => [MetaObjectType::Term, $termMeta->taxonomies],
            $owner(UserMeta::class) instanceof UserMeta => [MetaObjectType::User, []],
            $owner(CommentMeta::class) instanceof CommentMeta => [MetaObjectType::Comment, []],
            default => throw InvalidMetaDefinitionException::notADeclaration($class),
        };
    }

    private function buildDefinition(string $class, ReflectionProperty $property, Meta $meta, MetaObjectType $objectType): MetaDefinition
    {
        $name = $property->getName();
        $type = $property->getType();

        if (! $type instanceof ReflectionNamedType) {
            throw InvalidMetaDefinitionException::forProperty($class, $name, 'the property needs a single type (no union, no untyped property).');
        }

        [$valueType, $valueClass] = $this->resolveValueType($class, $name, $type);

        if (! $property->hasDefaultValue() && ! $type->allowsNull()) {
            throw InvalidMetaDefinitionException::forProperty($class, $name, 'give the property a default value or make it nullable: it is what an absent meta reads as.');
        }

        $key = $meta->resolveKey($name);

        if ($meta->showInRest && str_starts_with($key, '_') && $meta->capability === null) {
            throw InvalidMetaDefinitionException::forProperty($class, $name, sprintf(
                'the protected key "%s" can only be exposed in REST with an explicit capability.',
                $key
            ));
        }

        if ($meta->revisions && $objectType !== MetaObjectType::Post) {
            throw InvalidMetaDefinitionException::forProperty($class, $name, 'revisions only exist for post types.');
        }

        return new MetaDefinition(
            property: $name,
            key: $key,
            valueType: $valueType,
            valueClass: $valueClass,
            nullable: $type->allowsNull(),
            default: $property->hasDefaultValue() ? $property->getDefaultValue() : null,
            showInRest: $meta->showInRest,
            label: $meta->label,
            description: $meta->description,
            sanitize: $meta->sanitize,
            capability: $meta->capability,
            revisions: $meta->revisions,
            rules: array_values($meta->rules),
        );
    }

    /**
     * @return array{0: MetaValueType, 1: class-string|null}
     */
    private function resolveValueType(string $class, string $property, ReflectionNamedType $type): array
    {
        $typeName = $type->getName();

        $scalar = match ($typeName) {
            'string' => MetaValueType::String,
            'int' => MetaValueType::Integer,
            'float' => MetaValueType::Number,
            'bool' => MetaValueType::Boolean,
            default => null,
        };

        if ($scalar instanceof MetaValueType) {
            return [$scalar, null];
        }

        if (is_a($typeName, DateTimeInterface::class, true)) {
            return [MetaValueType::DateTime, $typeName];
        }

        if (is_a($typeName, BackedEnum::class, true)) {
            return [MetaValueType::Enum, $typeName];
        }

        $reason = is_a($typeName, UnitEnum::class, true)
            ? sprintf('the enum %s needs backing values (enum %s: string).', $typeName, class_basename($typeName))
            : sprintf('the type %s is not supported yet; use string, int, float, bool, a date or a backed enum.', $typeName);

        throw InvalidMetaDefinitionException::forProperty($class, $property, $reason);
    }
}
