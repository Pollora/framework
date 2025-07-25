<?php

declare(strict_types=1);

namespace Tests\Unit\Option\Domain\Services;

use PHPUnit\Framework\TestCase;
use Pollora\Option\Domain\Exceptions\InvalidOptionException;
use Pollora\Option\Domain\Services\OptionValidationService;

final class OptionValidationServiceTest extends TestCase
{
    private OptionValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OptionValidationService;
    }

    public function test_validates_valid_key(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->validateKey('valid_key');
    }

    public function test_throws_exception_for_empty_key(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Option key cannot be empty');

        $this->service->validateKey('');
    }

    public function test_throws_exception_for_too_long_key(): void
    {
        $longKey = str_repeat('a', 192);

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Option key cannot exceed 191 characters');

        $this->service->validateKey($longKey);
    }

    public function test_accepts_maximum_length_key(): void
    {
        $maxKey = str_repeat('a', 191);

        $this->expectNotToPerformAssertions();
        $this->service->validateKey($maxKey);
    }

    public function test_throws_exception_for_key_with_null_bytes(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Option key cannot contain null bytes');

        $this->service->validateKey("test\0key");
    }

    public function test_validates_valid_scalar_values(): void
    {
        $this->expectNotToPerformAssertions();

        $this->service->validateValue('string');
        $this->service->validateValue(42);
        $this->service->validateValue(3.14);
        $this->service->validateValue(true);
        $this->service->validateValue(null);
    }

    public function test_validates_valid_array_value(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->validateValue(['key' => 'value']);
    }

    public function test_validates_serializable_object(): void
    {
        $object = new \stdClass;
        $object->property = 'value';

        $this->expectNotToPerformAssertions();
        $this->service->validateValue($object);
    }

    public function test_throws_exception_for_resource_value(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Option value cannot be a resource');

        $this->service->validateValue($resource);

        fclose($resource);
    }

    public function test_throws_exception_for_non_serializable_object(): void
    {
        $closure = function () {
            return 'test';
        };

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Option value must be serializable');

        $this->service->validateValue($closure);
    }
}
