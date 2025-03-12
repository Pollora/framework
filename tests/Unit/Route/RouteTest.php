<?php

declare(strict_types=1);

namespace Tests\Unit\Route;

use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Matching\ConditionValidator;
use Pollora\Route\Route;

/**
 * Tests for the Route class that handles WordPress routes.
 */
class RouteTest extends TestCase
{
    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
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
     * Uses eval to define global functions that simulate WordPress conditional tags.
     *
     * @return void
     */
    private function mockWordPressFunctions(): void
    {
        // Use runkit or another mechanism to mock global functions
        // For tests, we'll simulate this behavior by checking if the functions already exist
        
        if (!function_exists('test_condition')) {
            eval('namespace { function test_condition($param = null) { return $param === 123; } }');
        }
    }

    /**
     * Test that the route is correctly marked as a WordPress route.
     *
     * @return void
     */
    public function testIsWordPressRoute()
    {
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
    }

    /**
     * Test that the WordPress condition is correctly defined and retrieved.
     *
     * @return void
     */
    public function testWordPressCondition()
    {
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
    }

    /**
     * Test that condition parameters are correctly defined and retrieved.
     *
     * @return void
     */
    public function testConditionParameters()
    {
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
    }

    /**
     * Test that the matches method works correctly for WordPress routes.
     *
     * @return void
     */
    public function testMatchesWordPressRoute()
    {
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
    }

    /**
     * Test that WordPress validators are correctly initialized.
     *
     * @return void
     */
    public function testWordPressValidators()
    {
        // Create a route
        $route = new Route(['GET'], 'test', function () {
            return 'test';
        });
        
        // Verify that WordPress validators are correctly initialized
        $validators = $route->getWordPressValidators();
        expect($validators)->toBeArray();
        expect($validators[0])->toBeInstanceOf(ConditionValidator::class);
    }

    /**
     * Test that the matches method uses WordPress validators for WordPress routes.
     *
     * @return void
     */
    public function testMatchesUsesWordPressValidators()
    {
        // Create a mock of the condition validator
        $validator = Mockery::mock(ConditionValidator::class);
        
        // Create a WordPress route
        $route = new Route(['GET'], 'is_page', function () {
            return 'page';
        });
        $route->setIsWordPressRoute(true);
        $route->setConditions(['is_page' => 'is_page']);
        
        // Replace WordPress validators with our mock
        $reflection = new \ReflectionProperty($route, 'wordpressValidators');
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
    }
} 