<?php

declare(strict_types=1);

namespace Pollora\Meta\Domain\Models;

use BackedEnum;
use Pollora\Meta\Domain\Enums\MetaValueType;
use ReflectionEnum;

/**
 * One typed meta, as declared by a `#[Meta]` property.
 *
 * Immutable: built once by the schema builder from the property and its
 * attribute, then read by the WordPress registry and by `Meta::of()`.
 */
final readonly class MetaDefinition
{
    /**
     * @param  string  $property  Name of the declaring property
     * @param  string  $key  Key stored in the database
     * @param  MetaValueType  $valueType  Kind of value, derived from the property type
     * @param  class-string|null  $valueClass  Enum or date class, for those value types
     * @param  bool  $nullable  Whether the property accepts null, which deletes the meta
     * @param  mixed  $default  Value returned when the meta is absent
     * @param  bool  $showInRest  Exposes the meta in the REST API
     * @param  string|null  $label  Label shown by the editor
     * @param  string|null  $description  Description passed to `register_meta()`
     * @param  string|array{0: class-string|object, 1: string}|null  $sanitize  Callable replacing the derived sanitization
     * @param  string|null  $capability  Capability required to write through REST and the editor
     * @param  bool  $revisions  Versions the meta with post revisions
     */
    public function __construct(
        public string $property,
        public string $key,
        public MetaValueType $valueType,
        public ?string $valueClass,
        public bool $nullable,
        public mixed $default,
        public bool $showInRest = false,
        public ?string $label = null,
        public ?string $description = null,
        public string|array|null $sanitize = null,
        public ?string $capability = null,
        public bool $revisions = false,
    ) {}

    /**
     * Whether WordPress treats the key as protected (hidden from custom fields and REST by default).
     */
    public function isProtected(): bool
    {
        return str_starts_with($this->key, '_');
    }

    /**
     * The `type` passed to `register_meta()`.
     */
    public function wordPressType(): string
    {
        return match ($this->valueType) {
            MetaValueType::String, MetaValueType::DateTime => 'string',
            MetaValueType::Integer => 'integer',
            MetaValueType::Number => 'number',
            MetaValueType::Boolean => 'boolean',
            MetaValueType::Enum => $this->isIntegerBackedEnum() ? 'integer' : 'string',
        };
    }

    /**
     * The JSON schema published with the meta in the REST API.
     *
     * @return array<string, mixed>
     */
    public function restSchema(): array
    {
        $schema = ['type' => $this->wordPressType()];

        if ($this->valueType === MetaValueType::DateTime) {
            $schema['format'] = 'date-time';
        }

        if ($this->valueType === MetaValueType::Enum) {
            /** @var class-string<BackedEnum> $enum */
            $enum = $this->valueClass;
            $schema['enum'] = array_map(static fn (BackedEnum $case): int|string => $case->value, $enum::cases());
        }

        return $schema;
    }

    private function isIntegerBackedEnum(): bool
    {
        /** @var class-string<BackedEnum> $enum */
        $enum = $this->valueClass;

        return (new ReflectionEnum($enum))->getBackingType()?->getName() === 'int';
    }
}
