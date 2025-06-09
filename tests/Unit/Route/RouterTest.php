<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery as m;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

/**
 * Setup function to create mocks and the router instance for all tests
 */
function setupRouterTest()
{
    // Initialize WordPress functions from helpers.php
    setupWordPressMocks();

    // Set up the event dispatcher mock
    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->andReturn(null);

    // Create container
    $container = new Container;

    // Configure the container with WordPress conditions
    $container->instance('config', new class
    {
        public function get($key, $default = null)
        {
            if ($key === 'wordpress.conditions') {
                return [
                    'is_page' => 'page',
                    'is_singular' => 'singular',
                    'is_archive' => 'archive',
                ];
            }

            return $default;
        }
    });

    // Create router
    $router = new ExtendedRouter($events, $container);

    // Mock WordPress classes
    mockWordPressClasses();

    return [
        'router' => $router,
        'events' => $events,
        'container' => $container,
    ];
}

/**
 * Mock WordPress classes
 */
function mockWordPressClasses(): void
{
    if (! class_exists('WP_Post')) {
        eval('namespace { class WP_Post { public function __construct($post = null) {} } }');
    }
}

/**
 * Clean up after each test
 */
afterEach(function () {
    Container::setInstance(null);
    WP::$wpFunctions = null;
    m::close();
});

/**
 * Test that the Router correctly creates new Route instances.
 */
test('router creates new route instances', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Create a route using the router
    $route = $router->get('/test', function () {
        return 'test';
    });

    // Verify that the route is an instance of our extended Route class
    expect($route)->toBeInstanceOf(Route::class);
    expect($route->uri())->toBe('test');
    expect($route->methods())->toContain('GET');
});

/**
 * Test that the Router can handle WordPress conditions.
 */
test('router manages WordPress conditions', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Test getting conditions
    $conditions = $router->getConditions();
    expect($conditions)->toBeArray();
    expect($conditions)->toHaveKey('page');
    expect($conditions['page'])->toBe('is_page');

    // Test resolving conditions
    expect($router->resolveCondition('page'))->toBe('is_page');
    expect($router->resolveCondition('unknown'))->toBe('unknown');
});

/**
 * Test that the Router can add WordPress bindings to routes.
 */
test('router adds WordPress bindings to routes', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Create a route with a closure that has WordPress dependencies
    $route = new Route(['GET'], 'test', function (\WP_Post $post) {
        return 'test';
    });

    // Add WordPress bindings
    $enhancedRoute = $router->addWordPressBindings($route);

    // The method should return the same route instance
    expect($enhancedRoute)->toBe($route);
    expect($enhancedRoute)->toBeInstanceOf(Route::class);
});

/**
 * Test that the Router creates routes with proper configuration.
 */
test('router creates WordPress-compatible routes', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Create a WordPress route
    $route = $router->get('/page', function () {
        return 'page';
    });

    // Set it as a WordPress route with condition
    $route->setIsWordPressRoute(true);
    $route->setCondition('is_page');

    // Verify the route configuration
    expect($route->isWordPressRoute())->toBeTrue();
    expect($route->getCondition())->toBe('is_page');
    expect($route->hasCondition())->toBeTrue();
});

/**
 * Test that the Router can create new Route instances with newRoute method.
 */
test('router newRoute method creates proper Route instances', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Use the newRoute method
    $route = $router->newRoute(['GET'], 'test', function () {
        return 'test';
    });

    // Verify that it returns our extended Route class
    expect($route)->toBeInstanceOf(Route::class);
    expect($route->uri())->toBe('test');
    expect($route->methods())->toContain('GET');

    // Verify that the route is properly configured
    expect($route->getAction())->toHaveKey('uses');
});
