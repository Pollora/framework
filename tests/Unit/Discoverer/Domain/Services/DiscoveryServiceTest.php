<?php

declare(strict_types=1);

namespace Tests\Unit\Discoverer\Domain\Services;

use PHPUnit\Framework\TestCase;
use Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface;
use Pollora\Discoverer\Domain\Services\DiscoveryService;

/**
 * Test suite for DiscoveryService.
 *
 * Tests the facade functionality of the DiscoveryService including
 * static method delegation, registry resolution, and error handling.
 */
final class DiscoveryServiceTest extends TestCase
{
    private ScoutRegistryInterface $mockRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRegistry = $this->createMock(ScoutRegistryInterface::class);
        DiscoveryService::setRegistry($this->mockRegistry);
    }

    protected function tearDown(): void
    {
        DiscoveryService::setRegistry(null);
        parent::tearDown();
    }

    public function test_register_delegates_to_registry(): void
    {
        $this->mockRegistry
            ->expects($this->once())
            ->method('register')
            ->with('test_scout', 'TestScoutClass');

        DiscoveryService::register('test_scout', 'TestScoutClass');
    }

    public function test_scout_delegates_to_registry(): void
    {
        $expectedCollection = collect(['TestClass1', 'TestClass2']);

        $this->mockRegistry
            ->expects($this->once())
            ->method('discover')
            ->with('test_scout')
            ->willReturn($expectedCollection);

        $result = DiscoveryService::scout('test_scout');

        $this->assertSame($expectedCollection, $result);
    }

    public function test_registered_delegates_to_registry(): void
    {
        $expectedKeys = ['scout1', 'scout2'];

        $this->mockRegistry
            ->expects($this->once())
            ->method('getRegistered')
            ->willReturn($expectedKeys);

        $result = DiscoveryService::registered();

        $this->assertEquals($expectedKeys, $result);
    }

    public function test_has_delegates_to_registry(): void
    {
        $this->mockRegistry
            ->expects($this->once())
            ->method('has')
            ->with('test_scout')
            ->willReturn(true);

        $result = DiscoveryService::has('test_scout');

        $this->assertTrue($result);
    }

    public function test_set_registry_allows_custom_registry(): void
    {
        $customRegistry = $this->createMock(ScoutRegistryInterface::class);
        $customRegistry
            ->expects($this->once())
            ->method('getRegistered')
            ->willReturn(['custom_scout']);

        DiscoveryService::setRegistry($customRegistry);

        $result = DiscoveryService::registered();
        $this->assertEquals(['custom_scout'], $result);
    }

    public function test_registry_resolution_with_null_function(): void
    {
        DiscoveryService::setRegistry(null);

        // Mock the app() function
        if (!function_exists('app')) {
            function app() {
                throw new \RuntimeException('Failed to resolve scout registry from container: Target [Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface] is not instantiable.');
            }
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to resolve scout registry from container: Target [Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface] is not instantiable.');

        DiscoveryService::registered();
    }

    public function test_registry_caching_between_calls(): void
    {
        $this->mockRegistry
            ->expects($this->exactly(2))
            ->method('getRegistered')
            ->willReturn(['test']);

        // Multiple calls should use the same registry instance
        DiscoveryService::registered();
        DiscoveryService::registered();
    }

    public function test_registry_reset_with_set_registry_null(): void
    {
        // First call establishes registry
        $this->mockRegistry
            ->expects($this->once())
            ->method('getRegistered')
            ->willReturn(['test']);

        DiscoveryService::registered();

        // Reset registry
        DiscoveryService::setRegistry(null);

        // New registry after reset
        $newRegistry = $this->createMock(ScoutRegistryInterface::class);
        $newRegistry
            ->expects($this->once())
            ->method('getRegistered')
            ->willReturn(['new_test']);

        DiscoveryService::setRegistry($newRegistry);

        $result = DiscoveryService::registered();
        $this->assertEquals(['new_test'], $result);
    }
}
