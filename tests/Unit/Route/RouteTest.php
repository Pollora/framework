<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Mockery as m;
use Pollora\Route\Infrastructure\Matching\ConditionValidator;
use Pollora\Route\Infrastructure\Adapters\Route;

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

    // Define the conditions
    $route->setConditions(['is_page' => 'is_page']);

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
    $route->setConditions(['test_condition' => 'test_condition']);

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
 * Test that WordPress validators are correctly initialized.
 */
test('route initializes WordPress validators correctly', function () {
    setupRouteTest();

    // Create a route
    $route = new Route(['GET'], 'test', function () {
        return 'test';
    });

    // Verify that WordPress validators are correctly initialized
    $validators = $route->getWordPressValidators();
    expect($validators)->toBeArray();
    expect($validators[0])->toBeInstanceOf(ConditionValidator::class);
});

/**
 * Test that the matches method uses WordPress validators for WordPress routes.
 */
test('route uses WordPress validators for matching', function () {
    setupRouteTest();

    // Create a mock of the condition validator
    $validator = m::mock(ConditionValidator::class);

    // Create a WordPress route
    $route = new Route(['GET'], 'is_page', function () {
        return 'page';
    });
    $route->setIsWordPressRoute(true);
    $route->setConditions(['is_page' => 'is_page']);

    // Replace WordPress validators with our mock
    $reflection = new ReflectionProperty($route, 'wordpressValidators');
    $reflection->setAccessible(true);
    $reflection->setValue($route, [$validator]);

    // Create a request
    $request = Request::create('/test', 'GET');

    // Configure the mock to return true
    $validator->shouldReceive('matches')
        ->once()
        ->with($route, $request)
        ->andReturn(true);

    // Verify that the matches method returns true
    expect($route->matches($request))->toBeTrue();

    // Configure the mock to return false
    $validator->shouldReceive('matches')
        ->once()
        ->with($route, $request)
        ->andReturn(false);

    // Verify that the matches method returns false
    expect($route->matches($request))->toBeFalse();
});
