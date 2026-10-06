<?php

declare(strict_types=1);

namespace Pollora\Meta\Infrastructure\Services;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Validation\Factory;
use Pollora\Meta\Domain\Contracts\MetaValidatorInterface;
use Pollora\Meta\Domain\Enums\MetaValueType;
use Pollora\Meta\Domain\Exceptions\MetaValidationException;
use Pollora\Meta\Domain\Models\MetaDefinition;

/**
 * Checks meta values with Laravel's validator.
 *
 * The type rule is implied (`integer` for an int…), so that size rules such as
 * `max:5000` compare numbers, not string lengths. An enum is checked by its
 * backing value; null on a nullable meta passes.
 */
final readonly class LaravelMetaValidator implements MetaValidatorInterface
{
    public function __construct(private Factory $validator) {}

    public function validate(MetaDefinition $definition, mixed $value): void
    {
        if ($definition->rules === []) {
            return;
        }

        $rules = [...$this->impliedRules($definition), ...$definition->rules];
        $validator = $this->validator->make(
            [$definition->key => $this->comparable($value)],
            [$definition->key => $rules],
            [],
            [$definition->key => $definition->label ?? str_replace('_', ' ', $definition->key)],
        );

        if ($validator->fails()) {
            throw new MetaValidationException($definition, array_values($validator->errors()->all()));
        }
    }

    /**
     * The value as rules compare it: enums by their backing value, data objects
     * as arrays of their public properties.
     */
    private function comparable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            is_array($value) => array_map($this->comparable(...), $value),
            is_object($value) && ! $value instanceof DateTimeInterface => array_map($this->comparable(...), get_object_vars($value)),
            default => $value,
        };
    }

    /**
     * @return list<string>
     */
    private function impliedRules(MetaDefinition $definition): array
    {
        $type = match ($definition->valueType) {
            MetaValueType::String => 'string',
            MetaValueType::Integer => 'integer',
            MetaValueType::Number => 'numeric',
            MetaValueType::Boolean => 'boolean',
            MetaValueType::DateTime => 'date',
            MetaValueType::Enum => null,
            MetaValueType::ArrayOf, MetaValueType::DataObject => 'array',
        };

        return array_values(array_filter([$definition->nullable ? 'nullable' : null, $type]));
    }
}
