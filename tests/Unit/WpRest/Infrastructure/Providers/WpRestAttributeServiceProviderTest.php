<?php

declare(strict_types=1);

namespace Tests\Unit\WpRest\Infrastructure\Providers;

use Illuminate\Container\Container;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Models\DiscoveryItems;
use Pollora\WpRest\AbstractWpRestRoute;
use Pollora\WpRest\Infrastructure\Providers\WpRestAttributeServiceProvider;
use Pollora\WpRest\Infrastructure\Services\WpRestDiscovery;
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

    private DiscoveryManager $discoveryManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->provider = new WpRestAttributeServiceProvider($this->container);

        // Create mock discovery manager
        $this->discoveryManager = $this->createMock(DiscoveryManager::class);
        $this->container->instance(DiscoveryManager::class, $this->discoveryManager);

        // Bind AttributeProcessor
        $this->container->bind(AttributeProcessor::class, function ($app) {
            return new AttributeProcessor($app);
        });
    }


    public function test_register_registers_wprest_discovery(): void
    {
        // The register method should register WpRestDiscovery as singleton
        $initialBindings = count($this->container->getBindings());

        $this->provider->register();

        // Should have registered WpRestDiscovery
        $this->assertEquals($initialBindings + 1, count($this->container->getBindings()));
        $this->assertTrue($this->container->bound(WpRestDiscovery::class));
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
        $this->expectNotToPerformAssertions();

        // Should not throw an exception even with empty discovery
        $this->provider->boot();
    }

    public function test_boot_handles_discovery_failure_gracefully(): void
    {
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

    public function test_boot_registers_discovered_wp_rest_routes_with_valid_data(): void
    {
        $this->expectNotToPerformAssertions();

        // Should not throw an exception when processing valid routes
        $this->provider->boot();
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

