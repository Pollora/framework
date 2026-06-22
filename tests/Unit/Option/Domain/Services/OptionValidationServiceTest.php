<?php

declare(strict_types=1);

use Pollora\Option\Domain\Services\OptionValidationService;
use Pollora\Option\InvalidOptionException;

describe('OptionValidationService', function (): void {
    beforeEach(function (): void {
        $this->service = new OptionValidationService;
    });

    it('validates valid key', function (): void {
        $this->service->validateKey('valid_key');
    })->throwsNoExceptions();

    it('throws exception for empty key', function (): void {
        $this->service->validateKey('');
    })->throws(InvalidOptionException::class, 'Option key cannot be empty');

    it('throws exception for too long key', function (): void {
        $this->service->validateKey(str_repeat('a', 192));
    })->throws(InvalidOptionException::class, 'Option key cannot exceed 191 characters');

    it('accepts maximum length key', function (): void {
        $this->service->validateKey(str_repeat('a', 191));
    })->throwsNoExceptions();

    it('throws exception for key with null bytes', function (): void {
        $this->service->validateKey("test\0key");
    })->throws(InvalidOptionException::class, 'Option key cannot contain null bytes');

    it('validates valid scalar values', function (): void {
        $this->service->validateValue('string');
        $this->service->validateValue(42);
        $this->service->validateValue(3.14);
        $this->service->validateValue(true);
        $this->service->validateValue(null);
    })->throwsNoExceptions();

    it('validates valid array value', function (): void {
        $this->service->validateValue(['key' => 'value']);
    })->throwsNoExceptions();

    it('validates serializable object', function (): void {
        $object = new stdClass;
        $object->property = 'value';

        $this->service->validateValue($object);
    })->throwsNoExceptions();

    it('throws exception for resource value', function (): void {
        $resource = fopen('php://memory', 'r');

        try {
            $this->service->validateValue($resource);
        } finally {
            fclose($resource);
        }
    })->throws(InvalidOptionException::class, 'Option value cannot be a resource');

    it('throws exception for non-serializable object', function (): void {
        $this->service->validateValue(fn (): string => 'test');
    })->throws(InvalidOptionException::class, 'Option value must be serializable');
});
