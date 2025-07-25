<?php

declare(strict_types=1);

namespace Tests\Unit\Option\Domain\Exceptions;

use PHPUnit\Framework\TestCase;
use Pollora\Option\Domain\Exceptions\OptionNotFoundException;

final class OptionNotFoundExceptionTest extends TestCase
{
    public function test_creates_exception_with_formatted_message(): void
    {
        $exception = new OptionNotFoundException('test_key');

        $this->assertEquals("Option 'test_key' not found", $exception->getMessage());
    }

    public function test_handles_special_characters_in_key(): void
    {
        $exception = new OptionNotFoundException('test-key_with.special');

        $this->assertEquals("Option 'test-key_with.special' not found", $exception->getMessage());
    }
}
