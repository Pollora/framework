<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;
use Pollora\Route\Infrastructure\Middleware\WordPressConditionMiddleware;

/**
 * Tests for WordPress routes with parameters.
 */
class WordPressRouteParametersTest extends TestCase
{
    private ExtendedRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize WordPress mocks
        setupWordPressMocks();
        
        $container = new Container();
        $dispatcher = $this->createMock(Dispatcher::class);
        
        $this->router = new ExtendedRouter($dispatcher, $container);
    }

    protected function tearDown(): void
    {
        resetWordPressMocks();
        parent::tearDown();
    }

    public function test_wp_route_with_single_parameter(): void
    {
        // Test: Route::wp('is_singular', 'realisations', function() {...})
        $route = $this->router->wp('is_singular', 'realisations', function () {
            return 'single realisations';
        });

        $action = $route->getAction();
        
        $this->assertTrue($action['wp_route']);
        $this->assertEquals('is_singular', $action['wp_condition']);
        $this->assertEquals(['realisations'], $action['wp_parameters']);
    }

    public function test_wp_route_with_multiple_parameters(): void
    {
        // Test: Route::wp('is_singular', ['post', 'page'], function() {...})
        $route = $this->router->wp('is_singular', ['post', 'page'], function () {
            return 'single post or page';
        });

        $action = $route->getAction();
        
        $this->assertTrue($action['wp_route']);
        $this->assertEquals('is_singular', $action['wp_condition']);
        $this->assertEquals(['post', 'page'], $action['wp_parameters']);
    }

    public function test_wp_route_without_parameters(): void
    {
        // Test: Route::wp('front', function() {...})
        $route = $this->router->wp('front', function () {
            return 'home';
        });

        $action = $route->getAction();
        
        $this->assertTrue($action['wp_route']);
        $this->assertEquals('front', $action['wp_condition']);
        $this->assertEquals([], $action['wp_parameters']);
    }

    public function test_wp_route_with_controller_and_parameters(): void
    {
        // Test: Route::wp('is_singular', 'realisations', 'PostController@show')
        $route = $this->router->wp('is_singular', 'realisations', 'PostController@show');

        $action = $route->getAction();
        
        $this->assertTrue($action['wp_route']);
        $this->assertEquals('is_singular', $action['wp_condition']);
        $this->assertEquals(['realisations'], $action['wp_parameters']);
        $this->assertEquals('PostController@show', $action['uses']);
    }

    public function test_middleware_passes_parameters_to_wordpress_function(): void
    {
        // Mock is_singular with parameter checking
        setWordPressFunction('is_singular', function ($post_type = null) {
            return $post_type === 'realisations';
        });
        
        $middleware = new WordPressConditionMiddleware();
        $request = Request::create('/', 'GET');
        
        // Create route with parameters
        $route = $this->router->wp('is_singular', 'realisations', function () {
            return 'success';
        });
        $request->setRouteResolver(fn() => $route);

        $response = $middleware->handle($request, function ($req) {
            return response('middleware passed');
        });

        $this->assertEquals('middleware passed', $response->getContent());
    }

    public function test_middleware_blocks_when_parameters_dont_match(): void
    {
        // Mock is_singular with parameter checking
        setWordPressFunction('is_singular', function ($post_type = null) {
            return $post_type === 'other_type'; // Only matches 'other_type'
        });
        
        $middleware = new WordPressConditionMiddleware();
        $request = Request::create('/', 'GET');
        
        // Create route that expects 'realisations' but function only accepts 'other_type'
        $route = $this->router->wp('is_singular', 'realisations', function () {
            return 'should not reach';
        });
        $request->setRouteResolver(fn() => $route);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $middleware->handle($request, function ($req) {
            return response('should not reach here');
        });
    }

    public function test_parse_route_arguments_various_signatures(): void
    {
        $router = $this->router;
        
        // Test private method via reflection
        $reflection = new \ReflectionClass($router);
        $method = $reflection->getMethod('parseWordPressRouteArguments');
        $method->setAccessible(true);

        // Test: wp('condition', $action)
        [$params, $action] = $method->invoke($router, [function () { return 'test'; }]);
        $this->assertEquals([], $params);
        $this->assertInstanceOf(\Closure::class, $action);

        // Test: wp('condition', 'param', $action)  
        [$params, $action] = $method->invoke($router, ['realisations', function () { return 'test'; }]);
        $this->assertEquals(['realisations'], $params);
        $this->assertInstanceOf(\Closure::class, $action);

        // Test: wp('condition', ['param1', 'param2'], $action)
        [$params, $action] = $method->invoke($router, [['post', 'page'], function () { return 'test'; }]);
        $this->assertEquals(['post', 'page'], $params);
        $this->assertInstanceOf(\Closure::class, $action);

        // Test: wp('condition', 'Controller@method') - no parameters
        [$params, $action] = $method->invoke($router, ['PostController@show']);
        $this->assertEquals([], $params);
        $this->assertEquals('PostController@show', $action);
    }
}