<?php

declare(strict_types=1);

namespace Tests\Unit\Option\Domain\Exceptions;

use PHPUnit\Framework\TestCase;
use Pollora\Option\Domain\Exceptions\InvalidOptionException;

final class InvalidOptionExceptionTest extends TestCase
{
    public function test_creates_exception_with_custom_message(): void
    {
        $message = 'Custom error message';
        $exception = new InvalidOptionException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function test_handles_empty_message(): void
    {
        $exception = new InvalidOptionException('');

        $this->assertEquals('', $exception->getMessage());
    }
}
