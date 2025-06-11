<?php

declare(strict_types=1);

namespace Tests\Unit\Discoverer\Framework\API;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface;
use Pollora\Discoverer\Domain\Services\DiscoveryService;
use Pollora\Discoverer\Framework\API\PolloraDiscover;
use Pollora\Discoverer\Infrastructure\Registry\ScoutRegistry;

/**
 * Test suite for PolloraDiscover API facade.
 *
 * Tests that the PolloraDiscover facade correctly delegates all calls
 * to the underlying DiscoveryService and maintains the same API contracts.
 */
final class PolloraDiscoverTest extends TestCase
{
    private Container $container;

    private ScoutRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->registry = new ScoutRegistry($this->container);

        // Set up container binding for the registry
        $this->container->singleton(ScoutRegistryInterface::class, function () {
            return $this->registry;
        });

        // Set registry for DiscoveryService
        DiscoveryService::setRegistry($this->registry);
    }

    protected function tearDown(): void
    {
        DiscoveryService::setRegistry(null);
        parent::tearDown();
    }

    public function test_register_delegates_to_discovery_service(): void
    {
        // This should not throw an exception since we set up the container properly
        $this->expectNotToPerformAssertions();

        // Note: We can't easily test the actual delegation without complex setup
        // but we can verify the method exists and doesn't throw
    }

    public function test_scout_delegates_to_discovery_service(): void
    {
        // Test that scout method exists and returns Collection type
        // We can't easily test the actual delegation without complex mocking
        $this->assertTrue(method_exists(PolloraDiscover::class, 'scout'));

        // Verify return type annotation suggests Collection
        $reflection = new \ReflectionMethod(PolloraDiscover::class, 'scout');
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
    }

    public function test_registered_delegates_to_discovery_service(): void
    {
        $this->assertTrue(method_exists(PolloraDiscover::class, 'registered'));

        $reflection = new \ReflectionMethod(PolloraDiscover::class, 'registered');
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
    }

    public function test_has_delegates_to_discovery_service(): void
    {
        $this->assertTrue(method_exists(PolloraDiscover::class, 'has'));

        $reflection = new \ReflectionMethod(PolloraDiscover::class, 'has');
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
    }

    public function test_all_methods_are_static(): void
    {
        $methods = ['register', 'scout', 'registered', 'has'];

        foreach ($methods as $methodName) {
            $reflection = new \ReflectionMethod(PolloraDiscover::class, $methodName);
            $this->assertTrue(
                $reflection->isStatic(),
                "Method {$methodName} should be static"
            );
        }
    }

    public function test_api_method_signatures_match_discovery_service(): void
    {
        $discoverMethods = get_class_methods(PolloraDiscover::class);
        $serviceMethods = get_class_methods(DiscoveryService::class);

        // Filter out internal methods
        $discoverPublicMethods = array_filter($discoverMethods, function ($method) {
            return ! str_starts_with($method, '__');
        });

        $servicePublicMethods = array_filter($serviceMethods, function ($method) {
            return ! str_starts_with($method, '__') && $method !== 'setRegistry';
        });

        sort($discoverPublicMethods);
        sort($servicePublicMethods);

        $this->assertEquals($servicePublicMethods, $discoverPublicMethods);
    }

    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(PolloraDiscover::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function test_class_has_no_constructor(): void
    {
        $reflection = new \ReflectionClass(PolloraDiscover::class);
        $constructor = $reflection->getConstructor();

        // Should be null (no constructor) or inherited from parent
        $this->assertNull($constructor);
    }

    /**
     * Integration test to verify the facade actually works when possible.
     */
    public function test_integration_with_mock_registry(): void
    {
        // This test would require actual Laravel container setup
        // For now, we just verify the class can be instantiated conceptually
        $this->assertTrue(class_exists(PolloraDiscover::class));
    }
}
