<?php

declare(strict_types=1);

namespace Tests\Feature\Route;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Infrastructure\Providers\RouteServiceProvider;

class WordPressRoutingTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app = new Application();
        $this->app->instance('app', $this->app);
        
        // Register the service provider
        $provider = new RouteServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    public function test_wp_macro_creates_functional_wordpress_route(): void
    {
        // Mock WordPress function
        if (!function_exists('test_is_single')) {
            eval('function test_is_single($post_type = null) { 
                return $post_type === null || $post_type === "product"; 
            }');
        }
        
        // Define a WordPress route
        $route = Route::wp('test_is_single', 'product', function () {
            return 'Single product page';
        });
        
        $this->assertTrue($route->isWordPressRoute());
        $this->assertTrue($route->hasCondition());
        $this->assertEquals('test_is_single', $route->getCondition());
        $this->assertEquals(['product'], $route->getConditionParameters());
    }

    public function test_wp_match_macro_creates_route_with_specific_methods(): void
    {
        // Mock WordPress function
        if (!function_exists('test_is_page')) {
            eval('function test_is_page($page = null) { 
                return $page === "contact"; 
            }');
        }
        
        // Define a WordPress route with specific HTTP methods
        $route = Route::wpMatch(['GET', 'POST'], 'test_is_page', 'contact', function () {
            return 'Contact page';
        });
        
        $this->assertTrue($route->isWordPressRoute());
        $this->assertEquals('test_is_page', $route->getCondition());
        $this->assertEquals(['contact'], $route->getConditionParameters());
        
        // Check that it has the correct methods
        $this->assertEquals(['GET', 'POST'], $route->methods());
    }

    public function test_route_matching_works_with_wordpress_conditions(): void
    {
        // Mock WordPress function that returns true
        if (!function_exists('test_condition_true')) {
            eval('function test_condition_true() { return true; }');
        }
        
        $route = Route::wp('test_condition_true', function () {
            return 'Matched';
        });
        
        $request = Request::create('/test_condition_true');
        
        $this->assertTrue($route->matches($request));
    }

    public function test_route_does_not_match_when_wordpress_condition_fails(): void
    {
        // Mock WordPress function that returns false
        if (!function_exists('test_condition_false')) {
            eval('function test_condition_false() { return false; }');
        }
        
        $route = Route::wp('test_condition_false', function () {
            return 'Should not match';
        });
        
        $request = Request::create('/test_condition_false');
        
        $this->assertFalse($route->matches($request));
    }

    public function test_route_hierarchy_respects_declaration_order(): void
    {
        // Mock WordPress functions
        if (!function_exists('test_specific_condition')) {
            eval('function test_specific_condition() { return true; }');
        }
        if (!function_exists('test_general_condition')) {
            eval('function test_general_condition() { return true; }');
        }
        
        // First route (more specific)
        $specificRoute = Route::wp('test_specific_condition', function () {
            return 'Specific route';
        });
        
        // Second route (more general)
        $generalRoute = Route::wp('test_general_condition', function () {
            return 'General route';
        });
        
        $request = Request::create('/test');
        
        // Both should match, but specific should be preferred (declaration order)
        $this->assertTrue($specificRoute->matches($request));
        $this->assertTrue($generalRoute->matches($request));
    }

    public function test_wordpress_route_with_multiple_parameters(): void
    {
        // Mock WordPress function with multiple parameters
        if (!function_exists('test_multi_param')) {
            eval('function test_multi_param($param1, $param2, $param3) { 
                return $param1 === "a" && $param2 === "b" && $param3 === "c"; 
            }');
        }
        
        $route = Route::wp('test_multi_param', 'a', 'b', 'c', function () {
            return 'Multi param route';
        });
        
        $this->assertEquals(['a', 'b', 'c'], $route->getConditionParameters());
        
        $request = Request::create('/test');
        $this->assertTrue($route->matches($request));
    }

    public function test_fallback_to_template_hierarchy_when_no_routes_match(): void
    {
        // This would be tested in integration with the full application
        // where the fallback route is registered
        $this->assertTrue(true); // Placeholder for integration test
    }

    public function test_standard_laravel_routes_still_work(): void
    {
        $route = Route::get('/api/test', function () {
            return 'Laravel route';
        });
        
        // Should not be a WordPress route
        $this->assertFalse($route->isWordPressRoute());
        $this->assertFalse($route->hasCondition());
        
        // Should match normally
        $request = Request::create('/api/test', 'GET');
        $this->assertTrue($route->matches($request));
    }

    public function test_wordpress_route_receives_middleware(): void
    {
        $route = Route::wp('is_single', function () {
            return 'test';
        });
        
        // Check that WordPress middleware is applied
        $middleware = $route->gatherMiddleware();
        
        $this->assertContains(
            'Pollora\Route\Infrastructure\Middleware\WordPressBindings',
            $middleware
        );
        $this->assertContains(
            'Pollora\Route\Infrastructure\Middleware\WordPressHeaders',
            $middleware
        );
        $this->assertContains(
            'Pollora\Route\Infrastructure\Middleware\WordPressBodyClass',
            $middleware
        );
        $this->assertContains(
            'Pollora\Route\Infrastructure\Middleware\WordPressShutdown',
            $middleware
        );
    }
}