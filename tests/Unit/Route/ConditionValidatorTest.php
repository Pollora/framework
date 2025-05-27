<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Route\Domain\Services\ConditionValidator;
use Pollora\Route\Domain\Models\RouteCondition;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Exceptions\InvalidRouteConditionException;

/**
 * Setup function to create the validator and mock dependencies
 */
function setupConditionValidatorTest()
{
    // Mock condition resolver
    $resolver = m::mock(ConditionResolverInterface::class);
    $resolver->shouldReceive('hasCondition')->with('custom_condition')->andReturn(true);
    $resolver->shouldReceive('hasCondition')->with('unknown_condition')->andReturn(false);
    $resolver->shouldReceive('hasCondition')->with('nonexistent_function')->andReturn(false);
    
    // Create the validator instance
    $validator = new ConditionValidator($resolver);

    // Mock WordPress functions
    mockWordPressFunctionsForValidator();

    return [
        'validator' => $validator,
        'resolver' => $resolver,
    ];
}

/**
 * Creates mocks for WordPress functions needed for validation tests
 */
function mockWordPressFunctionsForValidator(): void
{
    if (! function_exists('test_condition_with_params')) {
        eval('namespace { function test_condition_with_params($param1, $param2) { return $param1 === "value1" && $param2 === "value2"; } }');
    }

    if (! function_exists('test_condition_no_params')) {
        eval('namespace { function test_condition_no_params() { return true; } }');
    }

    if (! function_exists('test_condition_no_params_false')) {
        eval('namespace { function test_condition_no_params_false() { return false; } }');
    }

    if (! function_exists('test_condition_return_int')) {
        eval('namespace { function test_condition_return_int() { return 1; } }');
    }

    if (! function_exists('test_condition_return_string')) {
        eval('namespace { function test_condition_return_string() { return "string"; } }');
    }

    if (! function_exists('test_condition_return_empty_string')) {
        eval('namespace { function test_condition_return_empty_string() { return ""; } }');
    }

    if (! function_exists('test_condition_return_zero')) {
        eval('namespace { function test_condition_return_zero() { return 0; } }');
    }
}

/**
 * Clean up after each test
 */
afterEach(function () {
    m::close();
});

/**
 * Test that the validator throws exception if condition function doesn't exist.
 */
test('validator throws exception if condition function does not exist', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Create a condition with non-existent function
    $condition = RouteCondition::fromWordPressTag('nonexistent_function');

    // Verify that the validator throws exception
    expect(fn() => $validator->validate($condition))
        ->toThrow(InvalidRouteConditionException::class);
});

/**
 * Test that the validator validates existing WordPress conditions.
 */
test('validator validates existing wordpress conditions', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Create a condition with existing function
    $condition = RouteCondition::fromWordPressTag('test_condition_with_params', ['value1', 'value2']);

    // Verify that the validator returns true
    expect($validator->validate($condition))->toBeTrue();
    
    // Test condition without parameters
    $condition = RouteCondition::fromWordPressTag('test_condition_no_params');
    expect($validator->validate($condition))->toBeTrue();
});

/**
 * Test that the validator correctly validates different condition types.
 */
test('validator validates different condition types', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Test WordPress condition
    $condition = RouteCondition::fromWordPressTag('test_condition_no_params');
    expect($validator->validate($condition))->toBeTrue();

    // Test Laravel condition  
    $condition = RouteCondition::fromLaravel('/test');
    expect($validator->validate($condition))->toBeTrue();
    
    // Test custom condition
    $condition = RouteCondition::fromCustom('custom_condition');
    expect($validator->validate($condition))->toBeTrue();
});

/**
 * Test that the validator validates parameter types correctly.
 */
test('validator validates parameter types correctly', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Test valid parameters for is_page function
    expect($validator->validateConditionParameters('is_page', ['about']))->toBeTrue();
    expect($validator->validateConditionParameters('is_page', [123]))->toBeTrue();
    expect($validator->validateConditionParameters('is_page', [['about', 'contact']]))->toBeTrue();
    
    // Test invalid parameters  
    expect($validator->validateConditionParameters('is_page', [true]))->toBeFalse();
    
    // Test functions with no parameters allowed
    expect($validator->validateConditionParameters('is_home', []))->toBeTrue();
    expect($validator->validateConditionParameters('is_home', ['param']))->toBeFalse();
});

/**
 * Test that the validator safely checks conditions without throwing.
 */
test('validator safely checks conditions without throwing', function () {
    $setup = setupConditionValidatorTest();
    $validator = $setup['validator'];

    // Test safe validation of valid condition
    $condition = RouteCondition::fromWordPressTag('test_condition_no_params');
    expect($validator->isSafe($condition))->toBeTrue();
    
    // Test safe validation of invalid condition
    $condition = RouteCondition::fromWordPressTag('nonexistent_function');
    expect($validator->isSafe($condition))->toBeFalse();
    
    // Test getting validation errors
    $errors = $validator->getValidationErrors($condition);
    expect($errors)->toBeArray()
        ->and($errors)->not->toBeEmpty();
});
