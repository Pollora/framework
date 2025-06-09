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
 * Tests for WordPress route creation and middleware.
 */
class WordPressRouteTest extends TestCase
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

    public function test_wp_route_creation(): void
    {
        $route = $this->router->wp('front', function () {
            return 'home page';
        });

        $action = $route->getAction();
        
        $this->assertTrue($action['wp_route']);
        $this->assertEquals('front', $action['wp_condition']);
        $this->assertContains('wp.condition', $route->gatherMiddleware());
    }

    public function test_wp_route_with_controller(): void
    {
        $route = $this->router->wp('single', 'PostController@show');

        $action = $route->getAction();
        
        $this->assertTrue($action['wp_route']);
        $this->assertEquals('single', $action['wp_condition']);
        $this->assertEquals('PostController@show', $action['uses']);
    }

    public function test_wp_match_route(): void
    {
        $route = $this->router->wpMatch(['category', 'tag'], function () {
            return 'archive page';
        });

        $action = $route->getAction();
        
        $this->assertTrue($action['wp_route']);
        $this->assertEquals(['category', 'tag'], $action['wp_conditions']);
        $this->assertContains('wp.condition', $route->gatherMiddleware());
    }

    public function test_middleware_allows_matching_condition(): void
    {
        // Mock WordPress function to return true
        setWordPressFunction('is_front_page', fn() => true);
        
        $middleware = new WordPressConditionMiddleware();
        $request = Request::create('/', 'GET');
        
        // Mock route with WordPress condition
        $route = $this->router->wp('front', function () {
            return 'home';
        });
        $request->setRouteResolver(fn() => $route);

        $response = $middleware->handle($request, function ($req) {
            return response('success');
        });

        $this->assertEquals('success', $response->getContent());
    }

    public function test_middleware_blocks_non_matching_condition(): void
    {
        // Mock WordPress function to return false
        setWordPressFunction('is_front_page', fn() => false);
        
        $middleware = new WordPressConditionMiddleware();
        $request = Request::create('/some-page', 'GET');
        
        // Mock route with WordPress condition
        $route = $this->router->wp('front', function () {
            return 'home';
        });
        $request->setRouteResolver(fn() => $route);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $middleware->handle($request, function ($req) {
            return response('should not reach here');
        });
    }

    public function test_non_wordpress_routes_are_ignored_by_middleware(): void
    {
        $middleware = new WordPressConditionMiddleware();
        $request = Request::create('/', 'GET');
        
        // Mock regular Laravel route (no wp_route flag)
        $route = $this->router->get('/', function () {
            return 'regular route';
        });
        $request->setRouteResolver(fn() => $route);

        $response = $middleware->handle($request, function ($req) {
            return response('success');
        });

        $this->assertEquals('success', $response->getContent());
    }
}