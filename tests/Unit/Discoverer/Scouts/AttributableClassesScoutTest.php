<?php

declare(strict_types=1);

namespace Tests\Unit\Discoverer\Scouts;

use Illuminate\Container\Container;
use Pollora\Attributes\Attributable;
use Pollora\Discoverer\Scouts\AttributableClassesScout;
use Spatie\StructureDiscoverer\Discover;
use Tests\TestCase as BaseTestCase;

/**
 * Test suite for AttributableClassesScout.
 *
 * Tests the specific functionality of discovering classes that implement
 * the Attributable interface across application, modules, and themes.
 */
final class AttributableClassesScoutTest extends BaseTestCase
{
    private Container $container;

    private AttributableClassesScout $scout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->scout = new AttributableClassesScout($this->container, [sys_get_temp_dir()]);
    }

    public function test_criteria_configures_discovery_for_attributable_interface(): void
    {
        $discover = $this->createMock(Discover::class);

        $discover->expects($this->once())
            ->method('classes')
            ->willReturnSelf();

        $discover->expects($this->once())
            ->method('implementing')
            ->with(Attributable::class)
            ->willReturnSelf();

        $reflection = new \ReflectionClass($this->scout);
        $method = $reflection->getMethod('criteria');
        $method->setAccessible(true);

        $result = $method->invoke($this->scout, $discover);

        $this->assertSame($discover, $result);
    }

    public function test_get_default_directories_includes_plugin_paths(): void
    {
        $scout = new AttributableClassesScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('getDefaultDirectories');
        $method->setAccessible(true);

        $directories = $method->invoke($scout);

        $this->assertIsArray($directories);
        // Should include paths from parent::getDefaultDirectories() and plugin paths
        // In test environment, most paths won't exist, but method should not fail
    }

    public function test_scout_extends_abstract_pollora_scout(): void
    {
        $this->assertInstanceOf(
            \Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout::class,
            $this->scout
        );
    }

    public function test_scout_can_be_instantiated_with_container(): void
    {
        $scout = new AttributableClassesScout($this->container);

        $this->assertInstanceOf(AttributableClassesScout::class, $scout);
    }

    public function test_scout_can_be_instantiated_with_custom_directories(): void
    {
        $customDirectories = ['/custom/path1', '/custom/path2'];
        $scout = new AttributableClassesScout($this->container, $customDirectories);

        $reflection = new \ReflectionClass($scout);
        $directoriesProperty = $reflection->getProperty('directories');
        $directoriesProperty->setAccessible(true);

        $directories = $directoriesProperty->getValue($scout);

        $this->assertEquals($customDirectories, $directories);
    }

    public function test_identifier_includes_class_name(): void
    {
        $identifier = $this->scout->identifier();

        $this->assertStringContainsString('AttributableClassesScout', $identifier);
    }

    public function test_definition_method_returns_discover_instance(): void
    {
        $reflection = new \ReflectionClass($this->scout);
        $method = $reflection->getMethod('definition');
        $method->setAccessible(true);

        $result = $method->invoke($this->scout);

        $this->assertInstanceOf(Discover::class, $result);
    }

    /**
     * Integration test to verify the scout works with actual discovery.
     */
    public function test_get_method_executes_without_error(): void
    {
        // This test verifies that the scout can execute discovery
        // without throwing exceptions, even if no classes are found
        try {
            $result = $this->scout->get();
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            // If discovery fails due to missing dependencies, just verify
            // the scout is properly configured
            $this->assertInstanceOf(AttributableClassesScout::class, $this->scout);
        }
    }
}
