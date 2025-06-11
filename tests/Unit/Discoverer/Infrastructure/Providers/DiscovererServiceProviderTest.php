<?php

declare(strict_types=1);

namespace Tests\Unit\Discoverer\Infrastructure\Providers;

use Illuminate\Container\Container;
use Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface;
use Pollora\Discoverer\Framework\API\PolloraDiscover;
use Pollora\Discoverer\Infrastructure\Providers\DiscovererServiceProvider;
use Pollora\Discoverer\Infrastructure\Registry\ScoutRegistry;
use Pollora\Discoverer\Scouts\AttributableClassesScout;
use Pollora\Discoverer\Scouts\HookClassesScout;
use Pollora\Discoverer\Scouts\PostTypeClassesScout;
use Pollora\Discoverer\Scouts\TaxonomyClassesScout;
use Pollora\Discoverer\Scouts\WpRestRoutesScout;
use Tests\TestCase as BaseTestCase;

/**
 * Test suite for DiscovererServiceProvider.
 *
 * Tests the service provider registration, scout binding, and
 * bootstrap functionality of the discovery system.
 */
final class DiscovererServiceProviderTest extends BaseTestCase
{
    private Container $container;

    private DiscovererServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->provider = new DiscovererServiceProvider($this->container);
    }

    public function test_register_binds_scout_registry_interface(): void
    {
        $this->provider->register();

        $this->assertTrue($this->container->bound(ScoutRegistryInterface::class));

        $registry = $this->container->make(ScoutRegistryInterface::class);
        $this->assertInstanceOf(ScoutRegistry::class, $registry);
    }

    public function test_register_binds_scout_registry_as_singleton(): void
    {
        $this->provider->register();

        $registry1 = $this->container->make(ScoutRegistryInterface::class);
        $registry2 = $this->container->make(ScoutRegistryInterface::class);

        $this->assertSame($registry1, $registry2);
    }

    public function test_register_binds_all_core_scouts(): void
    {
        $this->provider->register();

        $expectedScouts = [
            AttributableClassesScout::class,
            HookClassesScout::class,
            PostTypeClassesScout::class,
            TaxonomyClassesScout::class,
            WpRestRoutesScout::class,
        ];

        foreach ($expectedScouts as $scoutClass) {
            $this->assertTrue(
                $this->container->bound($scoutClass),
                "Scout {$scoutClass} should be bound in container"
            );
        }
    }

    public function test_core_scouts_are_bound_as_singletons(): void
    {
        $this->provider->register();

        $scout1 = $this->container->make(AttributableClassesScout::class);
        $scout2 = $this->container->make(AttributableClassesScout::class);

        $this->assertSame($scout1, $scout2);
    }

    public function test_boot_registers_core_scouts_with_discovery_system(): void
    {
        // Use a real registry to avoid complex mocking issues
        $registry = new ScoutRegistry($this->container);
        $this->container->instance(ScoutRegistryInterface::class, $registry);

        // Set real registry for PolloraDiscover
        \Pollora\Discoverer\Domain\Services\DiscoveryService::setRegistry($registry);

        $this->provider->register();
        $this->provider->boot();

        // Verify all scouts are registered
        $expectedScouts = ['attributable', 'hooks', 'post_types', 'taxonomies', 'theme_providers', 'wp_rest_routes'];
        $registeredScouts = $registry->getRegistered();

        foreach ($expectedScouts as $scoutKey) {
            $this->assertTrue(
                $registry->has($scoutKey),
                "Scout '{$scoutKey}' should be registered"
            );
        }

        $this->assertCount(6, $registeredScouts);

        // Clean up
        \Pollora\Discoverer\Domain\Services\DiscoveryService::setRegistry(null);
    }

    public function test_provides_returns_correct_services(): void
    {
        $provided = $this->provider->provides();

        $this->assertContains(ScoutRegistryInterface::class, $provided);
        $this->assertContains(AttributableClassesScout::class, $provided);
        $this->assertContains(HookClassesScout::class, $provided);
        $this->assertContains(PostTypeClassesScout::class, $provided);
        $this->assertContains(TaxonomyClassesScout::class, $provided);
        $this->assertContains(WpRestRoutesScout::class, $provided);
    }

    public function test_provider_is_deferred(): void
    {
        // Check if provider has provides() method, indicating it's deferred
        $this->assertTrue(method_exists($this->provider, 'provides'));

        $provided = $this->provider->provides();
        $this->assertNotEmpty($provided);
    }

    public function test_provider_class_is_final(): void
    {
        $reflection = new \ReflectionClass(DiscovererServiceProvider::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function test_scout_instances_receive_container_dependency(): void
    {
        $this->provider->register();

        $scout = $this->container->make(AttributableClassesScout::class);

        // Verify the scout was created with container dependency
        $this->assertInstanceOf(AttributableClassesScout::class, $scout);

        // Check that the scout has access to the container
        $reflection = new \ReflectionClass($scout);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);

        $scoutContainer = $containerProperty->getValue($scout);
        $this->assertSame($this->container, $scoutContainer);
    }

    /**
     * Integration test to verify the full registration and boot process.
     */
    public function test_integration_register_and_boot(): void
    {
        // Set up a real registry for integration testing
        $registry = new ScoutRegistry($this->container);
        \Pollora\Discoverer\Domain\Services\DiscoveryService::setRegistry($registry);

        // This should complete without throwing exceptions
        $this->provider->register();
        $this->provider->boot();

        // Verify we can resolve the registry
        $resolvedRegistry = $this->container->make(ScoutRegistryInterface::class);
        $this->assertInstanceOf(ScoutRegistryInterface::class, $resolvedRegistry);

        // Verify scouts are available
        $scout = $this->container->make(AttributableClassesScout::class);
        $this->assertInstanceOf(AttributableClassesScout::class, $scout);

        // Clean up
        \Pollora\Discoverer\Domain\Services\DiscoveryService::setRegistry(null);
    }
}
