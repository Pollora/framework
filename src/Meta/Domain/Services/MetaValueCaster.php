<?php

declare(strict_types=1);

namespace Pollora\Meta\Domain\Services;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Pollora\Meta\Domain\Enums\MetaValueType;
use Pollora\Meta\Domain\Exceptions\InvalidMetaValueException;
use Pollora\Meta\Domain\Models\MetaDefinition;
use Stringable;
use Throwable;

/**
 * Converts meta values between their PHP type and the string WordPress stores.
 *
 * Reading is tolerant (a boolean accepts `1`, `true`, `yes`, `on`…), writing is
 * strict (the PHP value must match the property type). Dates are stored in
 * ISO 8601, in UTC.
 */
final class MetaValueCaster
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:sP';

    /**
     * The PHP value of a stored meta. An absent or empty value gives the default.
     *
     * @throws InvalidMetaValueException When the stored value cannot be read as the declared type
     */
    public function toPhp(MetaDefinition $definition, mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return $definition->default;
        }

        $value = match ($definition->valueType) {
            MetaValueType::String => is_scalar($raw) ? (string) $raw : null,
            MetaValueType::Integer => $this->readInteger($raw),
            MetaValueType::Number => is_numeric($raw) ? (float) $raw : null,
            MetaValueType::Boolean => is_scalar($raw) ? filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            MetaValueType::DateTime => $this->readDate($definition, $raw),
            MetaValueType::Enum => $this->readEnum($definition, $raw),
        };

        if ($value === null) {
            throw InvalidMetaValueException::forRead($definition, $raw);
        }

        return $value;
    }

    /**
     * The stored form of a value written from PHP. Null means the meta is deleted.
     *
     * @throws InvalidMetaValueException When the value does not match the property type
     */
    public function toStorage(MetaDefinition $definition, mixed $value): ?string
    {
        if ($value === null) {
            return $definition->nullable ? null : throw InvalidMetaValueException::forWrite($definition, $value);
        }

        $stored = match ($definition->valueType) {
            MetaValueType::String => is_string($value) || $value instanceof Stringable ? (string) $value : null,
            MetaValueType::Integer => is_int($value) ? (string) $value : null,
            MetaValueType::Number => is_int($value) || is_float($value) ? (string) $value : null,
            MetaValueType::Boolean => is_bool($value) ? ($value ? '1' : '0') : null,
            MetaValueType::DateTime => $value instanceof DateTimeInterface ? $this->formatDate($value) : null,
            MetaValueType::Enum => $value instanceof BackedEnum && $value::class === $definition->valueClass ? (string) $value->value : null,
        };

        return $stored ?? throw InvalidMetaValueException::forWrite($definition, $value);
    }

    /**
     * Normalizes any value written through WordPress (REST, editor, `update_post_meta()`)
     * to the stored form. Used as `sanitize_callback`: an unreadable value becomes
     * an empty string, which reads back as the default.
     */
    public function sanitize(MetaDefinition $definition, mixed $value): string
    {
        // Empty stays empty (an absent value), so sanitizing twice changes nothing.
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $phpValue = $value instanceof DateTimeInterface || $value instanceof BackedEnum
                ? $value
                : $this->toPhp($definition, $value);

            return $this->toStorage($definition, $phpValue) ?? '';
        } catch (InvalidMetaValueException) {
            return '';
        }
    }

    /**
     * The default as the REST API publishes it, or null when there is none.
     */
    public function toRestDefault(MetaDefinition $definition): int|float|bool|string|null
    {
        $default = $definition->default;

        return match (true) {
            $default === null => null,
            $default instanceof BackedEnum => $default->value,
            default => $default,
        };
    }

    private function readInteger(mixed $raw): ?int
    {
        $value = is_scalar($raw) ? filter_var($raw, FILTER_VALIDATE_INT) : false;

        return $value === false ? null : $value;
    }

    private function readDate(MetaDefinition $definition, mixed $raw): ?DateTimeInterface
    {
        if (! is_string($raw)) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($raw, 'UTC');
        } catch (Throwable) {
            return null;
        }

        $class = (string) $definition->valueClass;

        // A property typed with an interface (DateTimeInterface, CarbonInterface) gets a CarbonImmutable.
        return match (true) {
            is_a($class, DateTimeImmutable::class, true), is_a($class, DateTime::class, true) => $class::createFromInterface($date),
            default => $date,
        };
    }

    private function readEnum(MetaDefinition $definition, mixed $raw): ?BackedEnum
    {
        if (! is_scalar($raw)) {
            return null;
        }

        /** @var class-string<BackedEnum> $enum */
        $enum = $definition->valueClass;

        if ($definition->wordPressType() === 'integer') {
            $raw = $this->readInteger($raw);

            if ($raw === null) {
                return null;
            }
        }

        return $enum::tryFrom($raw);
    }

    private function formatDate(DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_FORMAT);
    }
}
