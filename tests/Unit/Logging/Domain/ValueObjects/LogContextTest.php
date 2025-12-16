<?php

declare(strict_types=1);

namespace Tests\Unit\Logging\Domain\ValueObjects;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pollora\Logging\Domain\ValueObjects\LogContext;
use RuntimeException;

/**
 * Test case for LogContext value object.
 *
 * @covers \Pollora\Logging\Domain\ValueObjects\LogContext
 */
#[CoversClass(LogContext::class)]
final class LogContextTest extends TestCase
{
    #[Test]
    public function it_creates_context_with_all_properties(): void
    {
        $exception = new RuntimeException('Test error');

        $context = new LogContext(
            module: 'Hook',
            class: 'TestClass',
            method: 'testMethod',
            extra: ['key' => 'value'],
            exception: $exception,
        );

        $this->assertSame('Hook', $context->module);
        $this->assertSame('TestClass', $context->class);
        $this->assertSame('testMethod', $context->method);
        $this->assertSame(['key' => 'value'], $context->extra);
        $this->assertSame($exception, $context->exception);
    }

    #[Test]
    public function it_creates_context_with_minimal_properties(): void
    {
        $context = new LogContext(module: 'Core');

        $this->assertSame('Core', $context->module);
        $this->assertNull($context->class);
        $this->assertNull($context->method);
        $this->assertSame([], $context->extra);
        $this->assertNull($context->exception);
    }

    #[Test]
    public function it_converts_to_array_with_all_properties(): void
    {
        $exception = new RuntimeException('Test error');

        $context = new LogContext(
            module: 'Hook',
            class: 'TestClass',
            method: 'testMethod',
            extra: ['key' => 'value'],
            exception: $exception,
        );

        $array = $context->toArray();

        $expected = [
            'pollora_module' => 'Hook',
            'class' => 'TestClass',
            'method' => 'testMethod',
            'exception' => $exception,
            'key' => 'value',
        ];

        $this->assertEquals($expected, $array);
    }

    #[Test]
    public function it_converts_to_array_with_minimal_properties(): void
    {
        $context = new LogContext(module: 'Core');

        $array = $context->toArray();

        $this->assertEquals(['pollora_module' => 'Core'], $array);
    }

    #[Test]
    public function it_creates_context_from_class_name(): void
    {
        $context = LogContext::fromClass(
            className: 'Pollora\\Hook\\Infrastructure\\Services\\HookDiscovery',
            method: 'apply'
        );

        $this->assertSame('Hook', $context->module);
        $this->assertSame('Pollora\\Hook\\Infrastructure\\Services\\HookDiscovery', $context->class);
        $this->assertSame('apply', $context->method);
        $this->assertSame([], $context->extra);
        $this->assertNull($context->exception);
    }

    #[Test]
    public function it_defaults_to_core_module_for_unknown_namespace(): void
    {
        $context = LogContext::fromClass('SomeClass');

        $this->assertSame('Core', $context->module);
        $this->assertSame('SomeClass', $context->class);
    }

    #[Test]
    public function it_extracts_module_from_various_namespaces(): void
    {
        $testCases = [
            ['Pollora\\Hook\\Services\\Test', 'Hook'],
            ['Pollora\\Discovery\\Test', 'Discovery'],
            ['Pollora\\PostType\\Models\\Custom', 'PostType'],
            ['App\\Services\\Test', 'Core'],
            ['Test', 'Core'],
        ];

        foreach ($testCases as [$className, $expectedModule]) {
            $context = LogContext::fromClass($className);
            $this->assertSame($expectedModule, $context->module, "Failed for class: {$className}");
        }
    }

    #[Test]
    public function it_creates_context_from_exception(): void
    {
        $exception = new RuntimeException('Test exception');

        $context = LogContext::fromException('TestModule', $exception, ['extra' => 'data']);

        $this->assertSame('TestModule', $context->module);
        $this->assertNull($context->class);
        $this->assertNull($context->method);
        $this->assertSame(['extra' => 'data'], $context->extra);
        $this->assertSame($exception, $context->exception);
    }

    #[Test]
    public function it_adds_extra_data_immutably(): void
    {
        $original = new LogContext(module: 'Test', extra: ['original' => 'value']);

        $modified = $original->withExtra(['new' => 'data']);

        $this->assertSame(['original' => 'value'], $original->extra);
        $this->assertSame(['original' => 'value', 'new' => 'data'], $modified->extra);
        $this->assertNotSame($original, $modified);
    }

    #[Test]
    public function it_adds_exception_immutably(): void
    {
        $original = new LogContext(module: 'Test');
        $exception = new RuntimeException('Test');

        $modified = $original->withException($exception);

        $this->assertNull($original->exception);
        $this->assertSame($exception, $modified->exception);
        $this->assertNotSame($original, $modified);
    }

    #[Test]
    public function extra_data_overwrites_existing_keys(): void
    {
        $context = new LogContext(module: 'Test', extra: ['key' => 'original']);

        $modified = $context->withExtra(['key' => 'new']);

        $this->assertSame(['key' => 'new'], $modified->extra);
    }
}
