<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Middleware\WordPressHeaders;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    $this->middleware = new WordPressHeaders;

    // Bind a config repository so config() helper works in unit tests
    $container = Container::getInstance();
    $container->instance('config', new Repository([
        'wordpress' => ['cache' => ['max_age' => 3600]],
    ]));
});

describe('framework header', function (): void {
    it('adds X-Powered-By Pollora header', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn (): null => null);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('content', 200, ['Content-Type' => 'text/html'])
        );

        expect($response->headers->get('X-Powered-By'))->toBe('Pollora');
    });

    it('wraps non-Symfony responses into SymfonyResponse', function (): void {
        $request = Request::create('/test');
        $request->setRouteResolver(fn (): null => null);

        $response = $this->middleware->handle($request, fn (): string => 'raw string');

        expect($response)->toBeInstanceOf(SymfonyResponse::class)
            ->and($response->getContent())->toBe('raw string');
    });
});

describe('WordPress header cleanup', function (): void {
    it('removes Cache-Control and Expires for non-WP routes with anonymous visitors', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('content', 200, [
                'Content-Type' => 'text/html',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT',
            ])
        );

        expect($response->headers->has('Expires'))->toBeFalse();
    });

    it('preserves Content-Type during cleanup', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('content', 200, [
                'Content-Type' => 'application/pdf',
            ])
        );

        expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    });

    it('skips cleanup for routes with WordPress conditions but still removes Expires on cache', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $inner = new SymfonyResponse('content', 200, [
            'Content-Type' => 'text/html',
            'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT',
        ]);

        $response = $this->middleware->handle($request, fn (): Response => $inner);

        // Cleanup is skipped (WP route), but Expires is removed by applyPublicCacheHeaders
        expect($response->headers->has('Expires'))->toBeFalse()
            ->and($response->headers->getCacheControlDirective('public'))->toBeTrue();
    });

    it('skips cleanup for authenticated users', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $inner = new SymfonyResponse('content', 200, [
            'Content-Type' => 'text/html',
            'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT',
        ]);

        $response = $this->middleware->handle($request, fn (): Response => $inner);

        expect($response->headers->has('Expires'))->toBeTrue();
    });
});

describe('public cache application', function (): void {
    it('applies public cache to HTML responses for anonymous visitors', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html'])
        );

        expect($response->headers->getCacheControlDirective('public'))->toBeTrue()
            ->and($response->headers->getCacheControlDirective('must-revalidate'))->toBeTrue()
            ->and($response->headers->getCacheControlDirective('max-age'))->toBe('3600');
    });

    it('skips cache for authenticated users', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn (): null => null);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html'])
        );

        expect($response->headers->getCacheControlDirective('public'))->toBeNull();
    });

    it('skips cache for JSON responses', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('{"data":true}', 200, ['Content-Type' => 'application/json'])
        );

        expect($response->headers->getCacheControlDirective('public'))->toBeNull();
    });

    it('skips cache for redirect responses', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('', 302, ['Location' => '/other'])
        );

        expect($response->headers->getCacheControlDirective('public'))->toBeNull();
    });

    it('skips cache for streamed responses', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): StreamedResponse => new StreamedResponse(fn (): int => print ('stream'), 200, ['Content-Type' => 'text/html'])
        );

        expect($response)->toBeInstanceOf(StreamedResponse::class)
            ->and($response->headers->getCacheControlDirective('public'))->toBeNull();
    });

    it('skips cache for empty responses (204)', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('', 204)
        );

        expect($response->headers->getCacheControlDirective('public'))->toBeNull();
    });
});

describe('explicit cache directive detection', function (): void {
    it('respects no-store directive', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $inner = new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html']);
        $inner->headers->addCacheControlDirective('no-store');

        $response = $this->middleware->handle($request, fn (): Response => $inner);

        expect($response->headers->getCacheControlDirective('public'))->toBeNull()
            ->and($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    });

    it('respects explicit max-age directive', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $inner = new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html']);
        $inner->setMaxAge(300);

        $response = $this->middleware->handle($request, fn (): Response => $inner);

        expect($response->headers->getCacheControlDirective('max-age'))->toBe('300');
    });

    it('respects explicit s-maxage directive', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $inner = new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html']);
        $inner->setSharedMaxAge(600);

        $response = $this->middleware->handle($request, fn (): Response => $inner);

        expect($response->headers->getCacheControlDirective('s-maxage'))->toBe('600');
    });

    it('respects private with max-age combination', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $inner = new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html']);
        $inner->setPrivate();
        $inner->setMaxAge(300);

        $response = $this->middleware->handle($request, fn (): Response => $inner);

        expect($response->headers->getCacheControlDirective('public'))->toBeNull()
            ->and($response->headers->getCacheControlDirective('max-age'))->toBe('300');
    });

    it('ignores WordPress nocache on non-WP routes after cleanup', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        // Simulate WordPress nocache headers
        $inner = new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html']);
        $inner->headers->addCacheControlDirective('no-store');
        $inner->headers->addCacheControlDirective('no-cache');

        $response = $this->middleware->handle($request, fn (): Response => $inner);

        // After cleanup, WordPress nocache is cleared and public cache is applied
        expect($response->headers->getCacheControlDirective('public'))->toBeTrue()
            ->and($response->headers->getCacheControlDirective('max-age'))->toBe('3600');
    });
});

describe('Expires header cleanup', function (): void {
    it('removes Expires header when applying public cache', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(true);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $inner = new SymfonyResponse('<html></html>', 200, [
            'Content-Type' => 'text/html',
            'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT',
        ]);

        $response = $this->middleware->handle($request, fn (): Response => $inner);

        expect($response->headers->has('Expires'))->toBeFalse()
            ->and($response->headers->getCacheControlDirective('public'))->toBeTrue();
    });
});

describe('per-condition TTL', function (): void {
    it('uses condition-specific TTL when matching', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);
        Brain\Monkey\Functions\when('is_front_page')->justReturn(true);

        $container = Container::getInstance();
        $container->instance('config', new Repository([
            'wordpress' => ['cache' => [
                'max_age' => 3600,
                'ttl' => ['is_front_page' => 600],
            ]],
        ]));

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html'])
        );

        expect($response->headers->getCacheControlDirective('max-age'))->toBe('600');
    });

    it('falls back to global max_age when no condition matches', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);
        Brain\Monkey\Functions\when('is_front_page')->justReturn(false);

        $container = Container::getInstance();
        $container->instance('config', new Repository([
            'wordpress' => ['cache' => [
                'max_age' => 3600,
                'ttl' => ['is_front_page' => 600],
            ]],
        ]));

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html'])
        );

        expect($response->headers->getCacheControlDirective('max-age'))->toBe('3600');
    });

    it('uses first matching condition in order', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);
        Brain\Monkey\Functions\when('is_front_page')->justReturn(true);
        Brain\Monkey\Functions\when('is_single')->justReturn(true);

        $container = Container::getInstance();
        $container->instance('config', new Repository([
            'wordpress' => ['cache' => [
                'max_age' => 3600,
                'ttl' => [
                    'is_front_page' => 600,
                    'is_single' => 7200,
                ],
            ]],
        ]));

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html'])
        );

        expect($response->headers->getCacheControlDirective('max-age'))->toBe('600');
    });
});

describe('shared_max_age (s-maxage)', function (): void {
    it('adds s-maxage when configured', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $container = Container::getInstance();
        $container->instance('config', new Repository([
            'wordpress' => ['cache' => [
                'max_age' => 3600,
                'shared_max_age' => 86400,
            ]],
        ]));

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html'])
        );

        expect($response->headers->getCacheControlDirective('s-maxage'))->toBe('86400')
            ->and($response->headers->getCacheControlDirective('max-age'))->toBe('3600');
    });

    it('does not add s-maxage when not configured', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('hasCondition')->andReturn(false);

        $request = Request::create('/test');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle(
            $request,
            fn (): Response => new SymfonyResponse('<html></html>', 200, ['Content-Type' => 'text/html'])
        );

        expect($response->headers->getCacheControlDirective('s-maxage'))->toBeNull();
    });
});
