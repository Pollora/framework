<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Middleware\WordPressBindings;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

class WordPressBindingsTest extends TestCase
{
    private WordPressBindings $middleware;
    private ExtendedRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->router = $this->createMock(ExtendedRouter::class);
        $this->middleware = new WordPressBindings($this->router);
    }

    public function test_it_adds_bindings_for_wordpress_routes(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('hasCondition')->willReturn(true);
        
        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);
        
        $this->router->expects($this->once())
            ->method('addWordPressBindings')
            ->with($route)
            ->willReturn($route);
        
        $next = function ($req) {
            return 'response';
        };
        
        $result = $this->middleware->handle($request, $next);
        
        $this->assertEquals('response', $result);
    }

    public function test_it_skips_bindings_for_non_wordpress_routes(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('hasCondition')->willReturn(false);
        
        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);
        
        $this->router->expects($this->never())
            ->method('addWordPressBindings');
        
        $next = function ($req) {
            return 'response';
        };
        
        $result = $this->middleware->handle($request, $next);
        
        $this->assertEquals('response', $result);
    }

    public function test_it_handles_request_without_route(): void
    {
        $request = Request::create('/test');
        $request->setRouteResolver(fn () => null);
        
        $this->router->expects($this->never())
            ->method('addWordPressBindings');
        
        $next = function ($req) {
            return 'response';
        };
        
        $result = $this->middleware->handle($request, $next);
        
        $this->assertEquals('response', $result);
    }

    public function test_it_handles_route_without_condition_method(): void
    {
        $route = new \stdClass(); // Route without hasCondition method
        
        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);
        
        $this->router->expects($this->never())
            ->method('addWordPressBindings');
        
        $next = function ($req) {
            return 'response';
        };
        
        $result = $this->middleware->handle($request, $next);
        
        $this->assertEquals('response', $result);
    }
}