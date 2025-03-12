<?php

declare(strict_types=1);

namespace Tests\Unit\Route;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Bindings\NullableWpPost;
use Pollora\Route\Route;
use Pollora\Route\Router;
use Pollora\Theme\TemplateHierarchy;

/**
 * Tests for the Router class that handles WordPress and Laravel routes.
 */
class RouterTest extends TestCase
{
    /**
     * @var Router The router instance being tested
     */
    protected $router;

    /**
     * @var Dispatcher The event dispatcher mock
     */
    protected $events;

    /**
     * @var Container The container instance
     */
    protected $container;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->events = Mockery::mock(Dispatcher::class);
        $this->events->shouldReceive('dispatch')->andReturn(null);
        
        $this->container = new Container();
        $this->container->instance('config', new class {
            public function get($key, $default = null)
            {
                return $default;
            }
        });
        
        $this->router = new Router($this->events, $this->container);
        
        // Define simulated WordPress functions globally
        $this->mockWordPressFunctions();
        
        // Mock the WP_Post class
        $this->mockWordPressClasses();
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
        
        if (!function_exists('is_page')) {
            // Define the function in the global namespace
            eval('namespace { function is_page() { return true; } }');
        }
        
        if (!function_exists('is_singular')) {
            eval('namespace { function is_singular() { return true; } }');
        }
        
        if (!function_exists('is_archive')) {
            eval('namespace { function is_archive() { return true; } }');
        }
    }
    
    /**
     * Creates mocks for WordPress classes.
     *
     * Defines the WP_Post class if it doesn't exist to avoid dependency issues.
     *
     * @return void
     */
    private function mockWordPressClasses(): void
    {
        // Define the WP_Post class if it doesn't exist
        if (!class_exists('WP_Post')) {
            eval('namespace { class WP_Post { public function __construct($post = null) {} } }');
        }
    }

    /**
     * Test that the Router correctly finds a standard Laravel route.
     *
     * @return void
     */
    public function testFindLaravelRoute()
    {
        // Create a request
        $request = Request::create('/test', 'GET');
        
        // Create a standard Laravel route
        $route = new Route(['GET'], 'test', function () {
            return 'test';
        });
        
        // Add the route to the router
        $this->router->getRoutes()->add($route);
        
        // Call the findRoute method
        $method = new \ReflectionMethod($this->router, 'findRoute');
        $method->setAccessible(true);
        $foundRoute = $method->invoke($this->router, $request);
        
        // Verify that the found route is the expected one
        expect($foundRoute)->toBe($route);
    }

    /**
     * Test that the Router correctly finds a WordPress route.
     *
     * @return void
     */
    public function testFindWordPressRoute()
    {
        // Create a request
        $request = Request::create('/test', 'GET');
        
        // Create WordPress routes
        $routePage = new Route(['GET'], 'is_page', function () {
            return 'page';
        });
        $routePage->setIsWordPressRoute(true);
        $routePage->setConditions(['is_page' => 'is_page']);
        
        $routeSingular = new Route(['GET'], 'is_singular', function () {
            return 'singular';
        });
        $routeSingular->setIsWordPressRoute(true);
        $routeSingular->setConditions(['is_singular' => 'is_singular']);
        
        // Add the routes to the router
        $this->router->getRoutes()->add($routePage);
        $this->router->getRoutes()->add($routeSingular);
        
        // Mock the getHierarchyOrder method of TemplateHierarchy
        $hierarchyOrder = ['is_page', 'is_singular', '__return_true'];
        
        // Use a partial mock to replace the static method
        $mock = Mockery::mock('alias:Pollora\Theme\TemplateHierarchy');
        $mock->shouldReceive('getHierarchyOrder')
            ->andReturn($hierarchyOrder);
        
        // Call the findRoute method
        $method = new \ReflectionMethod($this->router, 'findRoute');
        $method->setAccessible(true);
        $foundRoute = $method->invoke($this->router, $request);
        
        // Verify that the found route is the most specific one (is_page)
        expect($foundRoute)->toBe($routePage);
    }

    /**
     * Test that the Router chooses the most specific route (is_page) according to the WordPress hierarchy.
     *
     * @return void
     */
    public function testFindMostSpecificWordPressRouteWithPageFirst()
    {
        // Create a request
        $request = Request::create('/test', 'GET');
        
        // Create WordPress routes with different conditions
        $routePage = new Route(['GET'], 'is_page', function () {
            return 'page';
        });
        $routePage->setIsWordPressRoute(true);
        $routePage->setConditions(['is_page' => 'is_page']);
        
        $routeSingular = new Route(['GET'], 'is_singular', function () {
            return 'singular';
        });
        $routeSingular->setIsWordPressRoute(true);
        $routeSingular->setConditions(['is_singular' => 'is_singular']);
        
        $routeArchive = new Route(['GET'], 'is_archive', function () {
            return 'archive';
        });
        $routeArchive->setIsWordPressRoute(true);
        $routeArchive->setConditions(['is_archive' => 'is_archive']);
        
        // Add the routes to the router
        $this->router->getRoutes()->add($routePage);
        $this->router->getRoutes()->add($routeSingular);
        $this->router->getRoutes()->add($routeArchive);
        
        // Mock the getHierarchyOrder method of TemplateHierarchy
        $hierarchyOrder = ['is_page', 'is_singular', 'is_archive', '__return_true'];
        
        // Use a partial mock to replace the static method
        $mock = Mockery::mock('alias:Pollora\Theme\TemplateHierarchy');
        $mock->shouldReceive('getHierarchyOrder')
            ->andReturn($hierarchyOrder);
        
        // Call the findRoute method
        $method = new \ReflectionMethod($this->router, 'findRoute');
        $method->setAccessible(true);
        $foundRoute = $method->invoke($this->router, $request);
        
        // Verify that the found route is the most specific one (is_page)
        expect($foundRoute->getCondition())->toBe('is_page');
    }
    
    /**
     * Test that the Router chooses the most specific route (is_archive) according to the WordPress hierarchy.
     *
     * @return void
     */
    public function testFindMostSpecificWordPressRouteWithArchiveFirst()
    {
        // Create a request
        $request = Request::create('/test', 'GET');
        
        // Create WordPress routes with different conditions
        $routePage = new Route(['GET'], 'is_page', function () {
            return 'page';
        });
        $routePage->setIsWordPressRoute(true);
        $routePage->setConditions(['is_page' => 'is_page']);
        
        $routeSingular = new Route(['GET'], 'is_singular', function () {
            return 'singular';
        });
        $routeSingular->setIsWordPressRoute(true);
        $routeSingular->setConditions(['is_singular' => 'is_singular']);
        
        $routeArchive = new Route(['GET'], 'is_archive', function () {
            return 'archive';
        });
        $routeArchive->setIsWordPressRoute(true);
        $routeArchive->setConditions(['is_archive' => 'is_archive']);
        
        // Add the routes to the router
        $this->router->getRoutes()->add($routePage);
        $this->router->getRoutes()->add($routeSingular);
        $this->router->getRoutes()->add($routeArchive);
        
        // Mock the getHierarchyOrder method of TemplateHierarchy with a different order
        $hierarchyOrder = ['is_archive', 'is_page', 'is_singular', '__return_true'];
        
        // Use a partial mock to replace the static method
        $mock = Mockery::mock('alias:Pollora\Theme\TemplateHierarchy');
        $mock->shouldReceive('getHierarchyOrder')
            ->andReturn($hierarchyOrder);
        
        // Call the findRoute method
        $method = new \ReflectionMethod($this->router, 'findRoute');
        $method->setAccessible(true);
        $foundRoute = $method->invoke($this->router, $request);
        
        // Verify that the found route is the most specific one (is_archive)
        expect($foundRoute->getCondition())->toBe('is_archive');
    }

    /**
     * Test that the Router correctly creates a fallback route when no route is found.
     *
     * @return void
     */
    public function testNoRouteFound()
    {
        // Create a request for a route that doesn't exist
        $request = Request::create('/nonexistent', 'GET');
        
        // Call the findRoute method
        $method = new \ReflectionMethod($this->router, 'findRoute');
        $method->setAccessible(true);
        $foundRoute = $method->invoke($this->router, $request);
        
        // Verify that a fallback route is returned
        expect($foundRoute)->toBeInstanceOf(Route::class);
        
        // Verify that the fallback route uses the FrontendController
        $action = $foundRoute->getAction();
        expect($action['uses'])->toBe('Pollora\Http\Controllers\FrontendController@handle');
    }
} 