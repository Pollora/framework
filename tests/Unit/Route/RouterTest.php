<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Mockery as m;
use Pollora\Route\Infrastructure\Adapters\Route;
use Pollora\Route\Infrastructure\Adapters\Router;

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
    $router = new Router($events, $container);

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
 * Test that the Router correctly finds a standard Laravel route.
 */
test('router finds Laravel route', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Create a request
    $request = Request::create('/test', 'GET');

    // Create a standard Laravel route
    $route = new Route(['GET'], 'test', function () {
        return 'test';
    });

    // Add the route to the router
    $router->getRoutes()->add($route);

    // Call the findRoute method
    $method = new ReflectionMethod($router, 'findRoute');
    $method->setAccessible(true);
    $foundRoute = $method->invoke($router, $request);

    // Verify that the found route is the expected one
    expect($foundRoute)->toBe($route);
});

/**
 * Test that the Router correctly finds a WordPress route.
 */
test('router finds WordPress route', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Configure WordPress conditions with setWordPressConditions from helpers.php
    setWordPressConditions([
        'is_page' => true,
        'is_singular' => true,
        'is_archive' => false,
    ]);

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
    $router->getRoutes()->add($routePage);
    $router->getRoutes()->add($routeSingular);

    // Call the findRoute method
    $method = new ReflectionMethod($router, 'findRoute');
    $method->setAccessible(true);
    $foundRoute = $method->invoke($router, $request);

    // Verify that the found route is the most specific one (is_page)
    expect($foundRoute)->toBe($routePage);
});

/**
 * Test that the Router chooses the most specific route (is_page) according to the WordPress hierarchy.
 */
test('router finds most specific WordPress route with page first', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Configure WordPress conditions with setWordPressConditions from helpers.php
    setWordPressConditions([
        'is_page' => true,
        'is_singular' => true,
        'is_archive' => true,
    ]);

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
    $router->getRoutes()->add($routePage);
    $router->getRoutes()->add($routeSingular);
    $router->getRoutes()->add($routeArchive);

    // Call the findRoute method
    $method = new ReflectionMethod($router, 'findRoute');
    $method->setAccessible(true);
    $foundRoute = $method->invoke($router, $request);

    // Verify that the found route is the most specific one (is_page)
    expect($foundRoute->getCondition())->toBe('is_page');
});

/**
 * Test that the Router chooses the most specific route (is_archive) according to the WordPress hierarchy.
 */
test('router finds most specific WordPress route with archive first', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Important: seule la condition is_archive est vraie, les autres sont fausses
    setWordPressConditions([
        'is_page' => false,
        'is_singular' => false,
        'is_archive' => true,
    ]);

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

    // Add the routes to the router in specific order
    $router->getRoutes()->add($routePage);
    $router->getRoutes()->add($routeSingular);
    $router->getRoutes()->add($routeArchive);

    // Modify the config to prioritize archive first
    $container = $setup['container'];
    $container->instance('config', new class
    {
        public function get($key, $default = null)
        {
            if ($key === 'wordpress.conditions') {
                return [
                    'is_archive' => 'archive',
                    'is_page' => 'page',
                    'is_singular' => 'singular',
                ];
            }

            return $default;
        }
    });

    // Call the findRoute method
    $method = new ReflectionMethod($router, 'findRoute');
    $method->setAccessible(true);
    $foundRoute = $method->invoke($router, $request);

    // Verify that the found route is the most specific one (is_archive)
    expect($foundRoute->getCondition())->toBe('is_archive');
});

/**
 * Test that the Router correctly creates a fallback route when no route is found.
 */
test('router creates fallback when no route found', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Create a request
    $request = Request::create('/non-existent', 'GET');

    // Call the findRoute method
    $method = new ReflectionMethod($router, 'findRoute');
    $method->setAccessible(true);
    $foundRoute = $method->invoke($router, $request);

    // Verify that the route has the expected action
    expect($foundRoute->getAction('uses'))->toBe('Pollora\Http\Controllers\FrontendController@handle');
});

/**
 * Test that routes with parameters are prioritized over those without parameters.
 */
test('router prioritizes routes with parameters over routes without parameters', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Configure WordPress conditions to return true for is_singular
    setWordPressConditions([
        'is_singular' => true,
    ]);

    // Create a request
    $request = Request::create('/test', 'GET');

    // Create two routes with the same condition but one has parameters
    $routeWithoutParams = new Route(['GET'], 'is_singular', function () {
        return 'singular_no_params';
    });
    $routeWithoutParams->setIsWordPressRoute(true);
    $routeWithoutParams->setConditions(['is_singular' => 'is_singular']);
    $routeWithoutParams->setConditionParameters([]);

    $routeWithParams = new Route(['GET'], 'is_singular', function () {
        return 'singular_with_params';
    });
    $routeWithParams->setIsWordPressRoute(true);
    $routeWithParams->setConditions(['is_singular' => 'is_singular']);
    $routeWithParams->setConditionParameters(['post']);

    // Add routes in order: route without parameters first, then route with parameters
    // This tests that the prioritization logic works regardless of registration order
    $router->getRoutes()->add($routeWithoutParams);
    $router->getRoutes()->add($routeWithParams);

    // Call the findRoute method
    $method = new ReflectionMethod($router, 'findRoute');
    $method->setAccessible(true);
    $foundRoute = $method->invoke($router, $request);

    // Verify that the route with parameters is chosen over the one without
    expect($foundRoute)->toBe($routeWithParams)
        ->and($foundRoute->getConditionParameters())->not()->toBeEmpty();
});

/**
 * Test that hierarchy order is preserved among routes with the same parameter status.
 */
test('router respects hierarchy order among routes with same parameter status', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Configure WordPress conditions to return true for multiple conditions
    setWordPressConditions([
        'is_page' => true,
        'is_singular' => true,
    ]);

    // Create a request
    $request = Request::create('/test', 'GET');

    // Create two routes with different conditions but both have parameters
    $routePage = new Route(['GET'], 'is_page', function () {
        return 'page_with_params';
    });
    $routePage->setIsWordPressRoute(true);
    $routePage->setConditions(['is_page' => 'is_page']);
    $routePage->setConditionParameters(['123']);

    $routeSingular = new Route(['GET'], 'is_singular', function () {
        return 'singular_with_params';
    });
    $routeSingular->setIsWordPressRoute(true);
    $routeSingular->setConditions(['is_singular' => 'is_singular']);
    $routeSingular->setConditionParameters(['post']);

    // Add routes in reverse hierarchy order to test prioritization
    $router->getRoutes()->add($routeSingular);
    $router->getRoutes()->add($routePage);

    // Call the findRoute method
    $method = new ReflectionMethod($router, 'findRoute');
    $method->setAccessible(true);
    $foundRoute = $method->invoke($router, $request);

    // Verify that is_page is chosen over is_singular due to hierarchy order
    // (is_page appears before is_singular in the config)
    expect($foundRoute)->toBe($routePage)
        ->and($foundRoute->getCondition())->toBe('is_page');
});

/**
 * Test that WordPress routes with different parameters are handled correctly.
 * This test has been moved to ConditionValidatorTest to avoid function redeclaration issues.
 *
 * @see ConditionValidatorTest::validator_selects_correct_route_based_on_condition_parameters
 */
// test('router distinguishes between routes with same condition but different parameters', function () { ... })

/**
 * Test the specific case where is_singular('realisations') should be prioritized over is_single().
 */
test('router prioritizes is_singular with parameter over is_single without parameter', function () {
    $setup = setupRouterTest();
    $router = $setup['router'];

    // Configure WordPress conditions - both return true for a 'realisations' post
    setWordPressConditions([
        'is_singular' => true,  // is_singular('realisations') should return true
        'is_single' => true,    // is_single() should also return true for a single post
    ]);

    // Create a request
    $request = Request::create('/test', 'GET');

    // Create route for is_singular with 'realisations' parameter
    $routeSingularWithParam = new Route(['GET'], 'is_singular', function () {
        return 'singular_realisations';
    });
    $routeSingularWithParam->setIsWordPressRoute(true);
    $routeSingularWithParam->setConditions(['is_singular' => 'is_singular']);
    $routeSingularWithParam->setConditionParameters(['realisations']);

    // Create route for is_single without parameters
    $routeSingleWithoutParam = new Route(['GET'], 'is_single', function () {
        return 'single_generic';
    });
    $routeSingleWithoutParam->setIsWordPressRoute(true);
    $routeSingleWithoutParam->setConditions(['is_single' => 'is_single']);
    $routeSingleWithoutParam->setConditionParameters([]);

    // Update the container config to reflect WordPress hierarchy (is_single comes before is_singular)
    $container = $setup['container'];
    $container->instance('config', new class
    {
        public function get($key, $default = null)
        {
            if ($key === 'wordpress.conditions') {
                return [
                    'is_single' => 'single',      // This comes first in hierarchy
                    'is_singular' => 'singular',  // This comes second
                    'is_page' => 'page',
                    'is_archive' => 'archive',
                ];
            }

            return $default;
        }
    });

    // Add routes to the router
    $router->getRoutes()->add($routeSingleWithoutParam);
    $router->getRoutes()->add($routeSingularWithParam);

    // Call the findRoute method
    $method = new ReflectionMethod($router, 'findRoute');
    $method->setAccessible(true);
    $foundRoute = $method->invoke($router, $request);

    // Verify that is_singular('realisations') is chosen over is_single()
    // despite is_single having higher hierarchy priority, because routes with parameters
    // are globally prioritized over those without parameters
    expect($foundRoute)->toBe($routeSingularWithParam)
        ->and($foundRoute->getCondition())->toBe('is_singular')
        ->and($foundRoute->getConditionParameters())->toBe(['realisations']);
});
