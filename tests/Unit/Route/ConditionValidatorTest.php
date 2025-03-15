<?php

use Illuminate\Http\Request;
use Mockery;
use Pollora\Route\Matching\ConditionValidator;
use Pollora\Route\Route;

/**
 * Setup function to create the validator and mock WordPress functions
 */
function setupConditionValidatorTest()
{
    // Create the validator instance
    $validator = new ConditionValidator();

    // Mock WordPress functions
    mockWordPressFunctionsForValidator();

    return [
        'validator' => $validator
    ];
}

/**
 * Creates mocks for WordPress functions needed for validation tests
 */
function mockWordPressFunctionsForValidator(): void
{
    if (!function_exists('test_condition_with_params')) {
        eval('namespace { function test_condition_with_params($param1, $param2) { return $param1 === "value1" && $param2 === "value2"; } }');
    }

    if (!function_exists('test_condition_no_params')) {
        eval('namespace { function test_condition_no_params() { return true; } }');
    }

    if (!function_exists('test_condition_no_params_false')) {
        eval('namespace { function test_condition_no_params_false() { return false; } }');
    }

    if (!function_exists('test_condition_return_int')) {
        eval('namespace { function test_condition_return_int() { return 1; } }');
    }

    if (!function_exists('test_condition_return_string')) {
        eval('namespace { function test_condition_return_string() { return "string"; } }');
    }

    if (!function_exists('test_condition_return_empty_string')) {
        eval('namespace { function test_condition_return_empty_string() { return ""; } }');
    }

    if (!function_exists('test_condition_return_zero')) {
        eval('namespace { function test_condition_return_zero() { return 0; } }');
    }
}

/**
 * Clean up after each test
 */
afterEach(function () {
    Mockery::close();
});

/**
 * Test that the validator returns false if the condition function doesn't exist.
 */
test('validator returns false if condition function does not exist', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Create a mock of the route
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getCondition')->andReturn('nonexistent_function');

    // Create a request
    $request = Request::create('/test', 'GET');

    // Verify that the validator returns false
    expect($validator->matches($route, $request))->toBeFalse();
});

/**
 * Test that the validator calls the condition function with the correct parameters.
 */
test('validator calls condition function with correct parameters', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Create a mock of the route
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getCondition')->andReturn('test_condition_with_params');
    $route->shouldReceive('getConditionParameters')->andReturn(['value1', 'value2']);

    // Create a request
    $request = Request::create('/test', 'GET');

    // Verify that the validator returns true
    expect($validator->matches($route, $request))->toBeTrue();

    // Modify the parameters to make the condition return false
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getCondition')->andReturn('test_condition_with_params');
    $route->shouldReceive('getConditionParameters')->andReturn(['wrong', 'value2']);

    // Verify that the validator returns false
    expect($validator->matches($route, $request))->toBeFalse();
});

/**
 * Test that the validator correctly handles conditions without parameters.
 */
test('validator handles conditions without parameters', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Create a mock of the route
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getCondition')->andReturn('test_condition_no_params');
    $route->shouldReceive('getConditionParameters')->andReturn([]);

    // Create a request
    $request = Request::create('/test', 'GET');

    // Verify that the validator returns true
    expect($validator->matches($route, $request))->toBeTrue();

    // Modify the route to use the function that returns false
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getCondition')->andReturn('test_condition_no_params_false');
    $route->shouldReceive('getConditionParameters')->andReturn([]);

    // Verify that the validator returns false
    expect($validator->matches($route, $request))->toBeFalse();
});

/**
 * Test that the validator correctly converts different return types to boolean.
 */
test('validator converts various result types to boolean', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Create a request
    $request = Request::create('/test', 'GET');

    // Test each function with its expected boolean conversion
    $conditions = [
        'test_condition_return_int' => true,          // 1 converts to true
        'test_condition_return_string' => true,       // non-empty string converts to true
        'test_condition_return_empty_string' => false, // empty string converts to false
        'test_condition_return_zero' => false,        // 0 converts to false
    ];

    foreach ($conditions as $condition => $expected) {
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getCondition')->andReturn($condition);
        $route->shouldReceive('getConditionParameters')->andReturn([]);

        expect($validator->matches($route, $request))
            ->toBe($expected)
            ->and($condition)
            ->toBeString();
    }
});
