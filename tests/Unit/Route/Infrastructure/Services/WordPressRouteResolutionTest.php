<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

/**
 * Tests for WordPress route resolution based on conditions.
 *
 * This test suite replicates the route definitions from web.php
 * and verifies that each WordPress condition correctly matches
 * the appropriate route using the existing WordPress mock system.
 */
class WordPressRouteResolutionTest extends TestCase
{
    private ExtendedRouter $router;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize WordPress mocks system
        setupWordPressMocks();

        $this->container = new Container;
        $dispatcher = $this->createMock(Dispatcher::class);

        // Mock config for WordPress routing conditions - using the new format
        $config = $this->createMock(\Illuminate\Config\Repository::class);
        $config->method('get')
            ->willReturnCallback(function ($key, $default = null) {
                if ($key === 'wordpress.conditions') {
                    return [
                        'is_front_page' => 'front',
                        'is_home' => 'home',
                        'is_page' => 'page',
                        'is_single' => 'single',
                        'is_author' => 'author',
                        'is_category' => 'archive',
                        'is_page_template' => 'template',
                        'is_404' => ['404', 'not_found'],
                    ];
                }
                if ($key === 'wordpress.plugin_conditions') {
                    return [];
                }

                return $default;
            });

        $this->container->instance('config', $config);
        $this->router = new ExtendedRouter($dispatcher, $this->container);
    }

    protected function tearDown(): void
    {
        // Reset WordPress mocks after each test
        resetWordPressMocks();
        parent::tearDown();
    }

    /**
     * Helper to create a WordPress route with mocked functions
     */
    private function createWordPressRoute(string $condition, array $parameters = [], ?callable $action = null): Route
    {
        $action = $action ?: function () {
            return 'matched';
        };
        $resolvedCondition = $this->router->resolveCondition($condition);

        $route = new Route(['GET'], $condition, $action);
        $route->setIsWordPressRoute(true);
        $route->setCondition($resolvedCondition);
        $route->setConditionParameters($parameters);

        return $route;
    }

    public function test_route_condition_aliases_are_resolved_correctly(): void
    {
        // Test that aliases are correctly resolved - replicating the exact conditions from web.php
        $this->assertEquals('is_front_page', $this->router->resolveCondition('front'));
        $this->assertEquals('is_home', $this->router->resolveCondition('home'));
        $this->assertEquals('is_page', $this->router->resolveCondition('page'));
        $this->assertEquals('is_single', $this->router->resolveCondition('single'));
        $this->assertEquals('is_author', $this->router->resolveCondition('author'));
        $this->assertEquals('is_category', $this->router->resolveCondition('archive'));
        $this->assertEquals('is_page_template', $this->router->resolveCondition('template'));
        $this->assertEquals('is_404', $this->router->resolveCondition('404'));

        // Test that direct WordPress function names pass through unchanged
        $this->assertEquals('is_singular', $this->router->resolveCondition('is_singular'));
    }

    public function test_wordpress_route_is_marked_correctly(): void
    {
        // Define a WordPress route
        $wpRoute = $this->createWordPressRoute('front');

        // Define a regular Laravel route for comparison
        $laravelRoute = new Route(['GET'], '/test', function () {
            return 'test';
        });

        // Test that the WordPress route is correctly marked
        $this->assertTrue($wpRoute->isWordPressRoute());
        $this->assertFalse($laravelRoute->isWordPressRoute());
    }

    public function test_wordpress_route_has_correct_condition(): void
    {
        $route = $this->createWordPressRoute('front');

        $this->assertEquals('is_front_page', $route->getCondition());
        $this->assertTrue($route->hasCondition());
    }

    public function test_wordpress_route_with_parameters(): void
    {
        $route = $this->createWordPressRoute('is_singular', ['realisations']);

        $this->assertEquals('is_singular', $route->getCondition());
        $this->assertEquals(['realisations'], $route->getConditionParameters());
    }

    public function test_route_methods_and_properties(): void
    {
        $route = $this->createWordPressRoute('front');

        // Test basic route properties (Laravel automatically adds HEAD for GET routes)
        $this->assertEquals(['GET', 'HEAD'], $route->methods());
        $this->assertEquals('front', $route->uri());
        $this->assertTrue($route->isWordPressRoute());
        $this->assertTrue($route->hasCondition());
        $this->assertEquals('is_front_page', $route->getCondition());
        $this->assertEquals([], $route->getConditionParameters());
    }

    public function test_all_web_routes_have_correct_conditions(): void
    {
        // Test all route definitions from web.php
        $webRoutes = [
            'front' => 'is_front_page',           // Route::wp('front', ...)
            'is_singular' => 'is_singular',       // Route::wp('is_singular', 'realisations', ...)
            'home' => 'is_home',                  // Route::wp('home', ...)
            'template' => 'is_page_template',     // Route::wp('template', ...)
            'single' => 'is_single',              // Route::wp('single', ...)
            'page' => 'is_page',                  // Route::wp('page', ...)
            'author' => 'is_author',              // Route::wp('author', ...)
            'archive' => 'is_category',           // Route::wp('archive', ...)
            // Skip '404' route as it may not have a proper alias configured
        ];

        foreach ($webRoutes as $alias => $expectedCondition) {
            $route = $this->createWordPressRoute($alias);
            $this->assertEquals($expectedCondition, $route->getCondition(),
                "Route alias '{$alias}' should resolve to '{$expectedCondition}'");
        }
    }

    public function test_route_with_condition_parameters_from_web_routes(): void
    {
        // Test the specific route from web.php: Route::wp('is_singular', 'realisations', ...)
        $route = $this->createWordPressRoute('is_singular', ['realisations']);

        $this->assertEquals('is_singular', $route->getCondition());
        $this->assertEquals(['realisations'], $route->getConditionParameters());
        $this->assertTrue($route->isWordPressRoute());
    }

    public function test_404_route_condition(): void
    {
        // Test the 404 route - it now has an alias configured
        $resolvedCondition = $this->router->resolveCondition('404');

        // '404' should resolve to 'is_404' based on our configuration
        $this->assertEquals('is_404', $resolvedCondition);

        // Create route with '404' condition
        $route = $this->createWordPressRoute('404');
        $this->assertEquals('is_404', $route->getCondition());
        $this->assertTrue($route->isWordPressRoute());
    }

    public function test_route_matches_with_wordpress_condition_mocking(): void
    {
        // Test specific route matching using the WordPress mocking system

        // Test 1: front page route with is_front_page() = true
        setWordPressFunction('is_front_page', fn () => true);
        $frontRoute = $this->createWordPressRoute('front');
        $request = Request::create('/', 'GET');
        $this->assertTrue($frontRoute->matches($request));

        // Test 2: front page route with is_front_page() = false
        setWordPressFunction('is_front_page', fn () => false);
        $this->assertFalse($frontRoute->matches($request));

        // Test 3: single post route with is_single() = true
        setWordPressFunction('is_single', fn () => true);
        $singleRoute = $this->createWordPressRoute('single');
        $singleRequest = Request::create('/blog/article', 'GET');
        $this->assertTrue($singleRoute->matches($singleRequest));

        // Test 4: category route with is_category() = true
        setWordPressFunction('is_category', fn () => true);
        $categoryRoute = $this->createWordPressRoute('archive');
        $categoryRequest = Request::create('/category/news', 'GET');
        $this->assertTrue($categoryRoute->matches($categoryRequest));
    }

    public function test_route_with_parameters_using_wordpress_mocks(): void
    {
        // Test route with parameters - like Route::wp('is_singular', 'realisations', ...)
        // Note: The existing mock system doesn't fully support parameterized WordPress functions
        // so we'll test the route structure and basic condition matching

        $route = $this->createWordPressRoute('is_singular', ['realisations']);

        // Test that the route correctly stores the parameters
        $this->assertEquals(['realisations'], $route->getConditionParameters());
        $this->assertEquals('is_singular', $route->getCondition());
        $this->assertTrue($route->isWordPressRoute());

        // Mock is_singular to return true (simulating a match)
        setWordPressFunction('is_singular', fn () => true);

        $request = Request::create('/realisations/campus-vert', 'GET');

        // The route should match because our mock returns true
        $this->assertTrue($route->matches($request));

        // Test with mock returning false
        setWordPressFunction('is_singular', fn () => false);
        $this->assertFalse($route->matches($request));
    }

    public function test_multiple_conditions_simulation(): void
    {
        // Simulate different WordPress context scenarios from web.php

        // Scenario 1: Homepage
        setWordPressConditions([
            'is_front_page' => true,
            'is_home' => false,
            'is_page' => false,
            'is_single' => false,
            'is_category' => false,
            'is_404' => false,
        ]);

        $frontRoute = $this->createWordPressRoute('front');
        $homeRoute = $this->createWordPressRoute('home');

        $request = Request::create('/', 'GET');
        $this->assertTrue($frontRoute->matches($request));
        $this->assertFalse($homeRoute->matches($request));

        // Scenario 2: Blog archive
        setWordPressConditions([
            'is_front_page' => false,
            'is_home' => true,
            'is_page' => false,
            'is_single' => false,
            'is_category' => false,
            'is_404' => false,
        ]);

        $blogRequest = Request::create('/blog', 'GET');
        $this->assertFalse($frontRoute->matches($blogRequest));
        $this->assertTrue($homeRoute->matches($blogRequest));

        // Scenario 3: Category page
        setWordPressConditions([
            'is_front_page' => false,
            'is_home' => false,
            'is_page' => false,
            'is_single' => false,
            'is_category' => true,
            'is_404' => false,
        ]);

        $categoryRoute = $this->createWordPressRoute('archive');
        $categoryRequest = Request::create('/blog/category/actus', 'GET');
        $this->assertTrue($categoryRoute->matches($categoryRequest));
    }
}
