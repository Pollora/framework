<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Middleware\WordPressBindings;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

describe('WordPressBindings middleware', function (): void {
    beforeEach(function (): void {
        $this->router = Mockery::mock(ExtendedRouter::class);
        $this->middleware = new WordPressBindings($this->router);
    });

    it('adds bindings for WordPress routes', function (): void {
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $this->router->shouldReceive('addWordPressBindings')->once()->with($route)->andReturn($route);

        $result = $this->middleware->handle($request, fn ($req): string => 'response');

        expect($result)->toBe('response');
    });

    it('skips bindings for non-WordPress routes', function (): void {
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $this->router->shouldNotReceive('addWordPressBindings');

        $result = $this->middleware->handle($request, fn ($req): string => 'response');

        expect($result)->toBe('response');
    });

    it('handles request without route', function (): void {
        $request = Request::create('/test');
        $request->setRouteResolver(fn (): null => null);

        $this->router->shouldNotReceive('addWordPressBindings');

        $result = $this->middleware->handle($request, fn ($req): string => 'response');

        expect($result)->toBe('response');
    });

    it('handles route without condition method', function (): void {
        $route = new stdClass;

        $request = Request::create('/test');
        $request->setRouteResolver(fn (): stdClass => $route);

        $this->router->shouldNotReceive('addWordPressBindings');

        $result = $this->middleware->handle($request, fn ($req): string => 'response');

        expect($result)->toBe('response');
    });
});
