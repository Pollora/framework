<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Pollora\Route\Infrastructure\Providers\RouteServiceProvider;
use Pollora\Route\Infrastructure\Services\WordPressRoutingService;

beforeEach(function (): void {
    $routing = Mockery::mock(WordPressRoutingService::class);
    $routing->shouldReceive('resolveCondition')->andReturnArg(0);
    $this->app->instance(WordPressRoutingService::class, $routing);

    $provider = new RouteServiceProvider($this->app);
    $provider->register();

    $registerMacros = new ReflectionMethod($provider, 'registerWpMatchMacro');
    $registerMacros->invoke($provider);
    (new ReflectionMethod($provider, 'registerWpMacro'))->invoke($provider);

    $this->app['router']->middlewareGroup('web', [
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ]);
});

/** @return list<string> */
function gatheredMiddleware(): array
{
    $route = Route::middleware('web')->group(fn () => Route::wp('home', fn (): string => ''));

    return app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getRoutes()[0]);
}

describe('Route::wp() session middleware', function (): void {
    it('keeps the session stack of the web group by default', function (): void {
        $middleware = gatheredMiddleware();

        expect($middleware)->toContain(StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class);
    });

    it('leaves the session stack off when wordpress.sessions is false', function (): void {
        config(['wordpress.sessions' => false]);

        $middleware = gatheredMiddleware();

        expect($middleware)
            ->not->toContain(StartSession::class)
            ->not->toContain(ShareErrorsFromSession::class)
            ->not->toContain(PreventRequestForgery::class)
            ->not->toContain(ValidateCsrfToken::class)
            ->toContain(RouteServiceProvider::WORDPRESS_MIDDLEWARE[0]);
    });
});
