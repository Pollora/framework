<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

/**
 * Tests for lazy configuration loading in ExtendedRouter.
 *
 * This test verifies that the router can handle situations where
 * the configuration is not available during construction but becomes
 * available later during the application lifecycle.
 */
class LazyConfigLoadingTest extends TestCase
{
    public function test_router_works_without_config_during_construction(): void
    {
        // Create a container without config service bound
        $container = new Container;
        $dispatcher = $this->createMock(Dispatcher::class);

        // This should not throw an exception even though config is not available
        $router = new ExtendedRouter($dispatcher, $container);

        // Router should still have default conditions
        $conditions = $router->getConditions();
        $this->assertArrayHasKey('single', $conditions);
        $this->assertEquals('is_single', $conditions['single']);
    }

    public function test_router_loads_config_when_available_later(): void
    {
        // Create a container without config initially
        $container = new Container;
        $dispatcher = $this->createMock(Dispatcher::class);

        $router = new ExtendedRouter($dispatcher, $container);

        // Now bind the config service (simulating Laravel config being loaded later)
        $config = $this->createMock(\Illuminate\Config\Repository::class);
        $config->method('get')
            ->with('wordpress.routing.conditions', [])
            ->willReturn([
                'custom' => 'is_custom_condition',
                'special' => 'is_special_condition',
            ]);

        $container->instance('config', $config);

        // Now when we call resolveCondition, it should load the config
        $result = $router->resolveCondition('custom');
        $this->assertEquals('is_custom_condition', $result);

        // And it should have merged with defaults
        $conditions = $router->getConditions();
        $this->assertArrayHasKey('single', $conditions); // Default condition
        $this->assertArrayHasKey('custom', $conditions); // Config condition
        $this->assertEquals('is_single', $conditions['single']);
        $this->assertEquals('is_custom_condition', $conditions['custom']);
    }

    public function test_config_is_only_loaded_once(): void
    {
        $container = new Container;
        $dispatcher = $this->createMock(Dispatcher::class);

        $router = new ExtendedRouter($dispatcher, $container);

        // Mock config that should only be called once
        $config = $this->createMock(\Illuminate\Config\Repository::class);
        $config->expects($this->once())
            ->method('get')
            ->with('wordpress.routing.conditions', [])
            ->willReturn(['test' => 'is_test']);

        $container->instance('config', $config);

        // Call multiple times - config should only be loaded once
        $router->resolveCondition('test');
        $router->resolveCondition('test');
        $router->getConditions();
        $router->resolveCondition('another');
    }

    public function test_router_handles_config_exceptions_gracefully(): void
    {
        $container = new Container;
        $dispatcher = $this->createMock(Dispatcher::class);

        $router = new ExtendedRouter($dispatcher, $container);

        // Mock config that throws an exception
        $config = $this->createMock(\Illuminate\Config\Repository::class);
        $config->method('get')
            ->willThrowException(new \Exception('Config not ready'));

        $container->instance('config', $config);

        // Router should handle the exception gracefully and still work
        $result = $router->resolveCondition('single');
        $this->assertEquals('is_single', $result);

        $conditions = $router->getConditions();
        $this->assertArrayHasKey('single', $conditions);
    }

    public function test_config_merges_with_defaults_correctly(): void
    {
        $container = new Container;
        $dispatcher = $this->createMock(Dispatcher::class);

        $router = new ExtendedRouter($dispatcher, $container);

        // Mock config with limited conditions to test merge behavior
        $config = $this->createMock(\Illuminate\Config\Repository::class);
        $config->method('get')
            ->with('wordpress.routing.conditions', [])
            ->willReturn([
                'front' => 'is_front_page',
                'custom' => 'is_custom_condition',
            ]);

        $container->instance('config', $config);

        // Test that config conditions work
        $this->assertEquals('is_front_page', $router->resolveCondition('front'));
        $this->assertEquals('is_custom_condition', $router->resolveCondition('custom'));

        // Test that default conditions are preserved
        $this->assertEquals('is_single', $router->resolveCondition('single'));
        $this->assertEquals('is_date', $router->resolveCondition('date'));

        // Test that all conditions are available
        $conditions = $router->getConditions();
        $this->assertArrayHasKey('front', $conditions); // From config
        $this->assertArrayHasKey('custom', $conditions); // From config
        $this->assertArrayHasKey('single', $conditions); // From defaults
        $this->assertArrayHasKey('date', $conditions); // From defaults
    }
}
