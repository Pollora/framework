<?php

declare(strict_types=1);

namespace Tests\Unit\Option\Domain\Models;

use PHPUnit\Framework\TestCase;
use Pollora\Option\Domain\Models\Option;

final class OptionTest extends TestCase
{
    public function test_can_create_option_with_default_autoload(): void
    {
        $option = new Option('test_key', 'test_value');

        $this->assertEquals('test_key', $option->key);
        $this->assertEquals('test_value', $option->value);
        $this->assertTrue($option->autoload);
    }

    public function test_can_create_option_with_custom_autoload(): void
    {
        $option = new Option('test_key', 'test_value', false);

        $this->assertEquals('test_key', $option->key);
        $this->assertEquals('test_value', $option->value);
        $this->assertFalse($option->autoload);
    }

    public function test_can_create_option_with_different_value_types(): void
    {
        $stringOption = new Option('string_key', 'test');
        $intOption = new Option('int_key', 42);
        $arrayOption = new Option('array_key', ['foo' => 'bar']);
        $boolOption = new Option('bool_key', true);
        $nullOption = new Option('null_key', null);

        $this->assertEquals('test', $stringOption->value);
        $this->assertEquals(42, $intOption->value);
        $this->assertEquals(['foo' => 'bar'], $arrayOption->value);
        $this->assertTrue($boolOption->value);
        $this->assertNull($nullOption->value);
    }

    public function test_with_value_returns_new_instance(): void
    {
        $original = new Option('test_key', 'original_value');
        $updated = $original->withValue('new_value');

        $this->assertNotSame($original, $updated);
        $this->assertEquals('original_value', $original->value);
        $this->assertEquals('new_value', $updated->value);
        $this->assertEquals('test_key', $updated->key);
        $this->assertEquals($original->autoload, $updated->autoload);
    }

    public function test_with_autoload_returns_new_instance(): void
    {
        $original = new Option('test_key', 'test_value', true);
        $updated = $original->withAutoload(false);

        $this->assertNotSame($original, $updated);
        $this->assertTrue($original->autoload);
        $this->assertFalse($updated->autoload);
        $this->assertEquals($original->key, $updated->key);
        $this->assertEquals($original->value, $updated->value);
    }

    public function test_chaining_with_methods(): void
    {
        $original = new Option('test_key', 'original_value', true);
        $updated = $original
            ->withValue('new_value')
            ->withAutoload(false);

        $this->assertEquals('test_key', $updated->key);
        $this->assertEquals('new_value', $updated->value);
        $this->assertFalse($updated->autoload);

        $this->assertEquals('original_value', $original->value);
        $this->assertTrue($original->autoload);
    }
}
