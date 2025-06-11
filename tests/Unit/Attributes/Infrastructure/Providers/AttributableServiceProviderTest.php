<?php

declare(strict_types=1);

namespace Tests\Unit\Attributes\Infrastructure\Providers;

use Illuminate\Container\Container;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Attributes\Infrastructure\Providers\AttributableServiceProvider;
use Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface;
use Pollora\Discoverer\Domain\Services\DiscoveryService;
use Pollora\Discoverer\Infrastructure\Registry\ScoutRegistry;
use Spatie\StructureDiscoverer\StructureScout;
use Tests\TestCase as BaseTestCase;

/**
 * Test suite for AttributableServiceProvider.
 *
 * Tests the service provider functionality for discovering and registering
 * Attributable classes through the new discovery system.
 */
final class AttributableServiceProviderTest extends BaseTestCase
{
    private Container $container;

    private AttributableServiceProvider $provider;

    private ScoutRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->registry = new ScoutRegistry($this->container);
        $this->provider = new AttributableServiceProvider($this->container);

        // Set up discovery service
        DiscoveryService::setRegistry($this->registry);

        // Register the attributable scout manually for testing
        $this->registry->register('attributable', TestAttributableScout::class);
        $this->container->bind(TestAttributableScout::class, function () {
            return new TestAttributableScout([TestAttributableClass::class]);
        });

        // Bind AttributeProcessor
        $this->container->bind(AttributeProcessor::class, function ($app) {
            return new AttributeProcessor($app);
        });
    }

    protected function tearDown(): void
    {
        DiscoveryService::setRegistry(null);
        parent::tearDown();
    }

    public function test_register_does_not_register_services(): void
    {
        // The register method should not register any services
        $initialBindings = count($this->container->getBindings());

        $this->provider->register();

        $this->assertEquals($initialBindings, count($this->container->getBindings()));
    }

    public function test_boot_registers_discovered_attributable_classes(): void
    {
        // This test verifies that the boot method processes discovered Attributable classes
        $this->expectNotToPerformAssertions();

        // Should not throw an exception
        $this->provider->boot();
    }

    public function test_boot_handles_empty_discovery_gracefully(): void
    {
        // Override the scout to return empty results
        $this->registry->register('attributable', EmptyTestAttributableScout::class);
        $this->container->bind(EmptyTestAttributableScout::class, function () {
            return new EmptyTestAttributableScout([]);
        });

        $this->expectNotToPerformAssertions();

        $this->provider->boot();
    }

    public function test_boot_handles_discovery_failure_gracefully(): void
    {
        // Override the scout to throw an exception
        $this->registry->register('attributable', FailingTestAttributableScout::class);
        $this->container->bind(FailingTestAttributableScout::class, function () {
            return new FailingTestAttributableScout;
        });

        $this->expectNotToPerformAssertions();

        // Should not throw an exception even if discovery fails
        $this->provider->boot();
    }

    public function test_register_attributable_class_skips_invalid_classes(): void
    {
        // Bind a non-Attributable class
        $this->container->bind('InvalidClass', function () {
            return new \stdClass;
        });

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('registerAttributableClass');
        $method->setAccessible(true);

        $processor = new AttributeProcessor($this->container);

        // Should not throw an exception for invalid classes
        $this->expectNotToPerformAssertions();
        $method->invoke($this->provider, 'InvalidClass', $processor);
    }

    public function test_register_attributable_class_processes_valid_attributable_classes(): void
    {
        $this->container->bind(TestAttributableClass::class, function () {
            return new TestAttributableClass;
        });

        // Mock the AttributeProcessor to avoid WordPress function calls
        $mockProcessor = $this->createMock(AttributeProcessor::class);
        $mockProcessor->expects($this->once())
            ->method('process')
            ->with($this->isInstanceOf(TestAttributableClass::class));

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('registerAttributableClass');
        $method->setAccessible(true);

        $method->invoke($this->provider, TestAttributableClass::class, $mockProcessor);
    }
}

/**
 * Test Attributable class for testing purposes.
 */
class TestAttributableClass implements Attributable
{
    // Test implementation - no specific methods required by interface
}

/**
 * Test scout that returns test Attributable classes.
 */
class TestAttributableScout extends StructureScout
{
    public function __construct(private array $classes = []) {}

    protected function definition(): \Spatie\StructureDiscoverer\Discover
    {
        return \Spatie\StructureDiscoverer\Discover::in(sys_get_temp_dir());
    }

    public function get(): array
    {
        return $this->classes;
    }
}

/**
 * Test scout that returns empty results.
 */
class EmptyTestAttributableScout extends StructureScout
{
    public function __construct(private array $classes = []) {}

    protected function definition(): \Spatie\StructureDiscoverer\Discover
    {
        return \Spatie\StructureDiscoverer\Discover::in(sys_get_temp_dir());
    }

    public function get(): array
    {
        return [];
    }
}

/**
 * Test scout that throws exceptions.
 */
class FailingTestAttributableScout extends StructureScout
{
    protected function definition(): \Spatie\StructureDiscoverer\Discover
    {
        return \Spatie\StructureDiscoverer\Discover::in(sys_get_temp_dir());
    }

    public function get(): array
    {
        throw new \Exception('Discovery failed');
    }
}
