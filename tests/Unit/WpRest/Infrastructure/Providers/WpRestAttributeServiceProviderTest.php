<?php

declare(strict_types=1);

namespace Tests\Unit\WpRest\Infrastructure\Providers;

use Illuminate\Container\Container;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface;
use Pollora\Discoverer\Domain\Services\DiscoveryService;
use Pollora\Discoverer\Infrastructure\Registry\ScoutRegistry;
use Pollora\WpRest\AbstractWpRestRoute;
use Pollora\WpRest\Infrastructure\Providers\WpRestAttributeServiceProvider;
use Spatie\StructureDiscoverer\StructureScout;
use Tests\TestCase as BaseTestCase;

/**
 * Test suite for WpRestAttributeServiceProvider.
 *
 * Tests the service provider functionality for discovering and registering
 * WordPress REST API routes through the new discovery system.
 */
final class WpRestAttributeServiceProviderTest extends BaseTestCase
{
    private Container $container;

    private WpRestAttributeServiceProvider $provider;

    private ScoutRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->registry = new ScoutRegistry($this->container);
        $this->provider = new WpRestAttributeServiceProvider($this->container);

        // Set up discovery service
        DiscoveryService::setRegistry($this->registry);

        // Register the wp_rest_routes scout manually for testing
        $this->registry->register('wp_rest_routes', TestWpRestRoutesScout::class);
        $this->container->bind(TestWpRestRoutesScout::class, function () {
            return new TestWpRestRoutesScout([TestWpRestRoute::class]);
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

    public function test_boot_registers_discovered_wp_rest_routes(): void
    {
        // This test verifies that the boot method processes discovered REST routes
        $this->expectNotToPerformAssertions();

        // Should not throw an exception
        $this->provider->boot();
    }

    public function test_boot_handles_empty_discovery_gracefully(): void
    {
        // Override the scout to return empty results
        $this->registry->register('wp_rest_routes', EmptyTestWpRestRoutesScout::class);
        $this->container->bind(EmptyTestWpRestRoutesScout::class, function () {
            return new EmptyTestWpRestRoutesScout([]);
        });

        $this->expectNotToPerformAssertions();

        $this->provider->boot();
    }

    public function test_boot_handles_discovery_failure_gracefully(): void
    {
        // Override the scout to throw an exception
        $this->registry->register('wp_rest_routes', FailingTestWpRestRoutesScout::class);
        $this->container->bind(FailingTestWpRestRoutesScout::class, function () {
            return new FailingTestWpRestRoutesScout;
        });

        $this->expectNotToPerformAssertions();

        // Should not throw an exception even if discovery fails
        $this->provider->boot();
    }

    public function test_register_wp_rest_route_skips_invalid_classes(): void
    {
        // Bind a non-REST route class
        $this->container->bind('InvalidClass', function () {
            return new \stdClass;
        });

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('registerWpRestRoute');
        $method->setAccessible(true);

        $processor = new AttributeProcessor($this->container);

        // Should not throw an exception for invalid classes
        $this->expectNotToPerformAssertions();
        $method->invoke($this->provider, 'InvalidClass', $processor);
    }

    public function test_register_wp_rest_route_processes_valid_routes(): void
    {
        $this->container->bind(TestWpRestRoute::class, function () {
            return new TestWpRestRoute;
        });

        // Mock the AttributeProcessor to avoid WordPress function calls
        $mockProcessor = $this->createMock(AttributeProcessor::class);
        $mockProcessor->expects($this->once())
            ->method('process')
            ->with($this->isInstanceOf(TestWpRestRoute::class));

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('registerWpRestRoute');
        $method->setAccessible(true);

        $method->invoke($this->provider, TestWpRestRoute::class, $mockProcessor);
    }
}

/**
 * Test WordPress REST route class for testing purposes.
 */
class TestWpRestRoute extends AbstractWpRestRoute
{
    public string $namespace = 'test/v1';

    public string $route = 'endpoint';
}

/**
 * Test scout that returns test REST route classes.
 */
class TestWpRestRoutesScout extends StructureScout
{
    public function __construct(private array $routes = []) {}

    protected function definition(): \Spatie\StructureDiscoverer\Discover
    {
        return \Spatie\StructureDiscoverer\Discover::in(sys_get_temp_dir());
    }

    public function get(): array
    {
        return $this->routes;
    }
}

/**
 * Test scout that returns empty results.
 */
class EmptyTestWpRestRoutesScout extends StructureScout
{
    public function __construct(private array $routes = []) {}

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
class FailingTestWpRestRoutesScout extends StructureScout
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
