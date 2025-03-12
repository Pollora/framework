<?php

declare(strict_types=1);

namespace Tests\Unit\Route;

use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Matching\ConditionValidator;
use Pollora\Route\Route;

/**
 * Tests for the ConditionValidator class that validates WordPress conditions.
 */
class ConditionValidatorTest extends TestCase
{
    /**
     * @var ConditionValidator The validator instance being tested
     */
    protected $validator;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConditionValidator();
        
        // Define simulated WordPress functions globally
        $this->mockWordPressFunctions();
    }

    /**
     * Clean up the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Creates mocks for WordPress functions.
     *
     * Uses eval to define global functions that simulate WordPress conditional tags
     * with various return values for testing different scenarios.
     *
     * @return void
     */
    private function mockWordPressFunctions(): void
    {
        // Use runkit or another mechanism to mock global functions
        // For tests, we'll simulate this behavior by checking if the functions already exist
        
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
     * Test that the validator returns false if the condition function doesn't exist.
     *
     * @return void
     */
    public function testMatchesReturnsFalseIfConditionFunctionDoesNotExist()
    {
        // Create a mock of the route
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getCondition')->andReturn('nonexistent_function');
        
        // Create a request
        $request = Request::create('/test', 'GET');
        
        // Verify that the validator returns false
        expect($this->validator->matches($route, $request))->toBeFalse();
    }

    /**
     * Test that the validator calls the condition function with the correct parameters.
     *
     * @return void
     */
    public function testMatchesCallsConditionFunctionWithParameters()
    {
        // Create a mock of the route
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getCondition')->andReturn('test_condition_with_params');
        $route->shouldReceive('getConditionParameters')->andReturn(['value1', 'value2']);
        
        // Create a request
        $request = Request::create('/test', 'GET');
        
        // Verify that the validator returns true
        expect($this->validator->matches($route, $request))->toBeTrue();
        
        // Modify the parameters to make the condition return false
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getCondition')->andReturn('test_condition_with_params');
        $route->shouldReceive('getConditionParameters')->andReturn(['wrong', 'value2']);
        
        // Verify that the validator returns false
        expect($this->validator->matches($route, $request))->toBeFalse();
    }

    /**
     * Test that the validator correctly handles conditions without parameters.
     *
     * @return void
     */
    public function testMatchesHandlesConditionsWithoutParameters()
    {
        // Create a mock of the route
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getCondition')->andReturn('test_condition_no_params');
        $route->shouldReceive('getConditionParameters')->andReturn([]);
        
        // Create a request
        $request = Request::create('/test', 'GET');
        
        // Verify that the validator returns true
        expect($this->validator->matches($route, $request))->toBeTrue();
        
        // Modify the route to use the new function
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getCondition')->andReturn('test_condition_no_params_false');
        $route->shouldReceive('getConditionParameters')->andReturn([]);
        
        // Verify that the validator returns false
        expect($this->validator->matches($route, $request))->toBeFalse();
    }

    /**
     * Test that the validator correctly converts the result to a boolean.
     *
     * Tests various return types (int, string, empty string, zero) to ensure
     * they are properly converted to boolean values according to PHP's type casting rules.
     *
     * @return void
     */
    public function testMatchesConvertsResultToBoolean()
    {
        // Create a request
        $request = Request::create('/test', 'GET');
        
        // Test each function
        $conditions = [
            'test_condition_return_int' => true,
            'test_condition_return_string' => true,
            'test_condition_return_empty_string' => false,
            'test_condition_return_zero' => false,
        ];
        
        foreach ($conditions as $condition => $expected) {
            $route = Mockery::mock(Route::class);
            $route->shouldReceive('getCondition')->andReturn($condition);
            $route->shouldReceive('getConditionParameters')->andReturn([]);
            
            expect($this->validator->matches($route, $request))->toBe($expected);
        }
    }
} 