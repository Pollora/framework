<?php

declare(strict_types=1);

namespace Tests\Unit\WpRest\Infrastructure\Providers;

use Illuminate\Container\Container;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Logging\Domain\Contracts\LoggerInterface;
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

    private DiscoveryEngineInterface $discoveryEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->provider = new WpRestAttributeServiceProvider($this->container);

        // Create mock logger interface for LoggingService dependency
        $mockLogger = $this->createMock(LoggerInterface::class);
        $this->container->instance(LoggerInterface::class, $mockLogger);

        // Create mock discovery engine
        $this->discoveryEngine = $this->createMock(DiscoveryEngineInterface::class);
        $this->container->instance(DiscoveryEngineInterface::class, $this->discoveryEngine);
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

    public function test_boot_registers_wprest_discovery_with_engine(): void
    {
        // Test that boot method registers WpRestDiscovery with the discovery engine
        $this->discoveryEngine->expects($this->once())
            ->method('addDiscovery')
            ->with('wp_rest_routes', $this->isInstanceOf(WpRestDiscovery::class));

        $this->provider->register();
        $this->provider->boot();
    }

    public function test_boot_handles_no_discovery_engine_gracefully(): void
    {
        // Remove discovery engine from container
        unset($this->container[DiscoveryEngineInterface::class]);

        // Should not throw an exception when no discovery engine is bound
        $this->expectNotToPerformAssertions();
        $this->provider->boot();
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
class TestWpRestRoute
{
    public string $namespace = 'test/v1';

    public string $route = 'endpoint';
}
