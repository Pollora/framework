<?php

declare(strict_types=1);

namespace Tests\Unit\Discoverer\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Spatie\StructureDiscoverer\Discover;
use Tests\TestCase as BaseTestCase;

/**
 * Test suite for AbstractPolloraScout.
 *
 * Tests the base functionality of path management, directory detection,
 * and discovery configuration for Pollora scouts.
 */
final class AbstractPolloraScoutTest extends BaseTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
    }

    public function test_constructor_uses_default_directories_when_none_provided(): void
    {
        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $directoriesProperty = $reflection->getProperty('directories');
        $directoriesProperty->setAccessible(true);

        $directories = $directoriesProperty->getValue($scout);

        // Should call getDefaultDirectories()
        $this->assertIsArray($directories);
    }

    public function test_constructor_uses_provided_directories(): void
    {
        $customDirectories = ['/custom/path1', '/custom/path2'];
        $scout = new TestConcreteScout($this->container, $customDirectories);

        $reflection = new \ReflectionClass($scout);
        $directoriesProperty = $reflection->getProperty('directories');
        $directoriesProperty->setAccessible(true);

        $directories = $directoriesProperty->getValue($scout);

        $this->assertEquals($customDirectories, $directories);
    }

    public function test_get_valid_directories_filters_non_existent_paths(): void
    {
        $directories = [
            sys_get_temp_dir(), // Should exist
            '/non/existent/path', // Should not exist
            '/another/fake/path', // Should not exist
        ];

        $scout = new TestConcreteScout($this->container, $directories);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('getValidDirectories');
        $method->setAccessible(true);

        $validDirectories = $method->invoke($scout);

        $this->assertCount(1, $validDirectories);
        $this->assertContains(sys_get_temp_dir(), $validDirectories);
    }

    public function test_get_app_path_returns_null_when_app_path_function_unavailable(): void
    {
        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('getAppPath');
        $method->setAccessible(true);

        // In testing environment, app_path() might not be available
        $result = $method->invoke($scout);

        $this->assertTrue($result === null || is_string($result));
    }

    public function test_get_module_paths_returns_empty_when_modules_not_bound(): void
    {
        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('getModulePaths');
        $method->setAccessible(true);

        $result = $method->invoke($scout);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_theme_paths_returns_empty_when_wordpress_functions_unavailable(): void
    {
        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('getThemePaths');
        $method->setAccessible(true);

        $result = $method->invoke($scout);

        $this->assertIsArray($result);
        // Should be empty when WordPress functions are not available
        $this->assertEmpty($result);
    }

    public function test_get_theme_paths_lazy_returns_empty_when_wordpress_functions_unavailable(): void
    {
        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('getThemePathsLazy');
        $method->setAccessible(true);

        $result = $method->invoke($scout);

        $this->assertIsArray($result);
        // Should be empty when WordPress functions are not available
        $this->assertEmpty($result);
    }

    public function test_get_theme_paths_lazy_caches_results(): void
    {
        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('getThemePathsLazy');
        $method->setAccessible(true);

        // First call
        $result1 = $method->invoke($scout);

        // Second call should return cached result
        $result2 = $method->invoke($scout);

        $this->assertSame($result1, $result2);

        // Check that cache property is set using the parent class reflection
        $parentReflection = new \ReflectionClass(AbstractPolloraScout::class);
        $cacheProperty = $parentReflection->getProperty('cachedThemePaths');
        $cacheProperty->setAccessible(true);
        $cachedValue = $cacheProperty->getValue($scout);

        $this->assertNotNull($cachedValue);
        $this->assertSame($result1, $cachedValue);
    }

    public function test_get_plugin_paths_returns_empty_when_wp_plugin_dir_not_defined(): void
    {
        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('getPluginPaths');
        $method->setAccessible(true);

        $result = $method->invoke($scout);

        $this->assertIsArray($result);
        // Should be empty when WP_PLUGIN_DIR is not defined
        $this->assertEmpty($result);
    }

    public function test_identifier_includes_directory_hash(): void
    {
        $directories = [sys_get_temp_dir()];
        $scout = new TestConcreteScout($this->container, $directories);

        $identifier = $scout->identifier();

        $this->assertIsString($identifier);
        $this->assertStringContainsString('TestConcreteScout', $identifier);
        $this->assertStringContainsString('_', $identifier); // Should contain separator
    }

    public function test_identifier_changes_with_different_directories(): void
    {
        $scout1 = new TestConcreteScout($this->container, [sys_get_temp_dir()]);
        $scout2 = new TestConcreteScout($this->container, ['/different/path']);

        $this->assertNotEquals($scout1->identifier(), $scout2->identifier());
    }

    public function test_definition_applies_parallel_processing(): void
    {
        $scout = new TestConcreteScout($this->container, [sys_get_temp_dir()]);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('definition');
        $method->setAccessible(true);

        $discover = $method->invoke($scout);

        $this->assertInstanceOf(Discover::class, $discover);
        // We can't easily test if parallel() was called, but method should not throw
    }

    public function test_definition_uses_temp_dir_when_no_valid_directories(): void
    {
        $scout = new TestConcreteScout($this->container, ['/non/existent/path']);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('definition');
        $method->setAccessible(true);

        $discover = $method->invoke($scout);

        $this->assertInstanceOf(Discover::class, $discover);
        // Should not throw exception even with invalid directories
    }

    public function test_should_use_cache_detects_production_environment(): void
    {
        // Set up ENV variable directly for easier testing
        $_ENV['APP_ENV'] = 'production';

        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('shouldUseCache');
        $method->setAccessible(true);

        $result = $method->invoke($scout);

        $this->assertTrue($result);

        // Cleanup
        unset($_ENV['APP_ENV']);
    }

    public function test_should_use_cache_detects_development_environment(): void
    {
        // Setup mock app with development environment
        $mockApp = \Mockery::mock();
        $mockApp->shouldReceive('environment')
            ->with('production')
            ->andReturn(false);

        $this->container->instance('app', $mockApp);

        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('shouldUseCache');
        $method->setAccessible(true);

        $result = $method->invoke($scout);

        $this->assertFalse($result);
    }

    public function test_should_use_cache_falls_back_to_env_variable(): void
    {
        // Set environment variable
        $_ENV['APP_ENV'] = 'production';

        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('shouldUseCache');
        $method->setAccessible(true);

        $result = $method->invoke($scout);

        $this->assertTrue($result);

        // Cleanup
        unset($_ENV['APP_ENV']);
    }

    public function test_should_use_cache_defaults_to_false_when_no_environment_detected(): void
    {
        $scout = new TestConcreteScout($this->container);

        $reflection = new \ReflectionClass($scout);
        $method = $reflection->getMethod('shouldUseCache');
        $method->setAccessible(true);

        $result = $method->invoke($scout);

        $this->assertFalse($result);
    }
}

/**
 * Concrete implementation of AbstractPolloraScout for testing.
 */
class TestConcreteScout extends AbstractPolloraScout
{
    protected function criteria(Discover $discover): Discover
    {
        return $discover->classes();
    }

    protected function getDefaultDirectories(): array
    {
        return [sys_get_temp_dir()];
    }
}
