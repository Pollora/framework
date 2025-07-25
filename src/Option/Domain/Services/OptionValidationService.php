<?php

declare(strict_types=1);

namespace Pollora\Option\Domain\Services;

use Pollora\Option\Domain\Exceptions\InvalidOptionException;

/**
 * Service for validating option keys and values.
 */
final class OptionValidationService
{
    private const MAX_KEY_LENGTH = 191;

    private const MIN_KEY_LENGTH = 1;

    /**
     * Validate an option key.
     *
     * @param  string  $key  The option key to validate
     *
     * @throws InvalidOptionException If the key is invalid
     */
    public function validateKey(string $key): void
    {
        if (strlen($key) < self::MIN_KEY_LENGTH) {
            throw new InvalidOptionException('Option key cannot be empty');
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new InvalidOptionException('Option key cannot exceed '.self::MAX_KEY_LENGTH.' characters');
        }

        if (str_contains($key, "\0")) {
            throw new InvalidOptionException('Option key cannot contain null bytes');
        }
    }

    /**
     * Validate an option value.
     *
     * @param  mixed  $value  The option value to validate
     *
     * @throws InvalidOptionException If the value is invalid
     */
    public function validateValue(mixed $value): void
    {
        if (is_resource($value)) {
            throw new InvalidOptionException('Option value cannot be a resource');
        }

        if (is_object($value) && ! $this->isSerializableObject($value)) {
            throw new InvalidOptionException('Option value must be serializable');
        }
    }

    /**
     * Check if an object is serializable.
     */
    private function isSerializableObject(object $object): bool
    {
        try {
            serialize($object);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
