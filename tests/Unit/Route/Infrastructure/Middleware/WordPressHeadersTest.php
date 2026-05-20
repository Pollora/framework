<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Pollora\Route\Infrastructure\Middleware\WordPressHeaders;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

describe('WordPressHeaders middleware', function (): void {
    beforeEach(function (): void {
        setupWordPressMocks();
        $this->middleware = new WordPressHeaders;
    });

    it('throws TypeError when next handler returns a non-SymfonyResponse', function (): void {
        $request = Request::create('/test');

        expect(fn () => $this->middleware->handle(
            $request,
            fn ($req) => 'not-a-symfony-response'
        ))->toThrow(TypeError::class);
    });

    it('sets public cache headers for a guest on a non-WordPress route', function (): void {
        setWordPressFunction('is_user_logged_in', fn (): bool => false);

        $route = Mockery::mock();
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $innerResponse = new SymfonyResponse;
        $response = $this->middleware->handle($request, fn ($req) => $innerResponse);

        expect($response->headers->get('X-Powered-By'))->toBe('Pollora');
        expect($response->headers->getCacheControlDirective('max-age'))->toBe('3600');
        expect($response->headers->hasCacheControlDirective('must-revalidate'))->toBeTrue();
        expect($response->headers->hasCacheControlDirective('public'))->toBeTrue();
    });

    it('does not set public cache headers for a logged-in user', function (): void {
        setWordPressFunction('is_user_logged_in', fn (): bool => true);

        $request = Request::create('/test');

        $innerResponse = new SymfonyResponse;
        $response = $this->middleware->handle($request, fn ($req) => $innerResponse);

        expect($response->headers->getCacheControlDirective('max-age'))->toBeNull();
    });

    it('always adds the X-Powered-By Pollora header', function (): void {
        setWordPressFunction('is_user_logged_in', fn (): bool => true);

        $request = Request::create('/test');

        $innerResponse = new SymfonyResponse;
        $response = $this->middleware->handle($request, fn ($req) => $innerResponse);

        expect($response->headers->get('X-Powered-By'))->toBe('Pollora');
    });
});
