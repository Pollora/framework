<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Mockery as m;
use Pollora\Route\Domain\Models\Route;

/**
 * Setup function to create test environment for route tests
 */
function setupRouteTest()
{
    // Define simulated WordPress functions
    mockWordPressFunctionsForRoute();

    return [];
}

/**
 * Mock WordPress functions needed for route tests
 */
function mockWordPressFunctionsForRoute(): void
{
    if (! function_exists('test_condition')) {
        eval('namespace { function test_condition($param = null) { return $param === 123; } }');
    }
}

/**
 * Clean up after each test
 */
afterEach(function () {
    m::close();
});

/**
 * Test that the route is correctly marked as a WordPress route.
 */
test('route can be marked as WordPress route', function () {
    setupRouteTest();

    // Create a route
    $route = new Route(['GET'], 'test', function () {
        return 'test';
    });

    // By default, it's not a WordPress route
    expect($route->isWordPressRoute())->toBeFalse();

    // Mark as WordPress route
    $route->setIsWordPressRoute(true);

    // Verify that it's now a WordPress route
    expect($route->isWordPressRoute())->toBeTrue();
});

/**
 * Test that the WordPress condition is correctly defined and retrieved.
 */
test('route can define and retrieve WordPress condition', function () {
    setupRouteTest();

    // Create a WordPress route
    $route = new Route(['GET'], 'is_page', function () {
        return 'page';
    });
    $route->setIsWordPressRoute(true);

    // Define the condition
    $route->setCondition('is_page');

    // Verify that the condition is correctly defined
    expect($route->getCondition())->toBe('is_page');
    expect($route->hasCondition())->toBeTrue();
});

/**
 * Test that condition parameters are correctly defined and retrieved.
 */
test('route can define and retrieve condition parameters', function () {
    setupRouteTest();

    // Create a WordPress route
    $route = new Route(['GET'], 'is_page', function () {
        return 'page';
    });
    $route->setIsWordPressRoute(true);

    // Define condition parameters
    $params = [123];
    $route->setConditionParameters($params);

    // Verify that the parameters are correctly defined
    expect($route->getConditionParameters())->toBe($params);
});

/**
 * Test that the matches method works correctly for WordPress routes.
 */
test('route matches correctly for WordPress conditions', function () {
    setupRouteTest();

    // Create a WordPress route
    $route = new Route(['GET'], 'test_condition', function () {
        return 'test';
    });
    $route->setIsWordPressRoute(true);
    $route->setCondition('test_condition');

    // Create a request
    $request = Request::create('/test', 'GET');

    // Case 1: Without parameters, the condition returns false
    expect($route->matches($request))->toBeFalse();

    // Case 2: With the correct parameter, the condition returns true
    $route->setConditionParameters([123]);
    expect($route->matches($request))->toBeTrue();

    // Case 3: With an incorrect parameter, the condition returns false
    $route->setConditionParameters([456]);
    expect($route->matches($request))->toBeFalse();
});

/**
 * Test that route can check if it has WordPress conditions.
 */
test('route can check if it has WordPress conditions', function () {
    setupRouteTest();

    // Create a route without conditions
    $route = new Route(['GET'], 'test', function () {
        return 'test';
    });

    // Should not have condition initially
    expect($route->hasCondition())->toBeFalse();

    // Set a condition
    $route->setCondition('is_page');

    // Should now have condition
    expect($route->hasCondition())->toBeTrue();
});

/**
 * Test that the route correctly evaluates WordPress conditions during matching.
 */
test('route evaluates WordPress conditions correctly', function () {
    setupRouteTest();

    // Create a WordPress route with a condition that exists
    $route = new Route(['GET'], 'is_page', function () {
        return 'page';
    });
    $route->setIsWordPressRoute(true);
    $route->setCondition('is_page');

    // Create a request
    $request = Request::create('/test', 'GET');

    // Since is_page() function exists in helpers and returns true by default
    // the route should match
    expect($route->matches($request))->toBeTrue();

    // Test with a non-existent condition
    $route->setCondition('nonexistent_function');
    expect($route->matches($request))->toBeFalse();
});
