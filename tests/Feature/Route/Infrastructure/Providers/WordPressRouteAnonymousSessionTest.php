<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\RouteCollection;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Pollora\Route\Infrastructure\Providers\RouteServiceProvider;
use Pollora\Route\Infrastructure\Services\WordPressRoutingService;

/**
 * Measures what an anonymous visitor costs on a `Route::wp()` route declared
 * in the `web` group, with the `database` session driver: one request is sent
 * through the real session and cookie middleware (issue #559).
 */
beforeEach(function (): void {
    Brain\Monkey\Functions\when('is_front_page')->justReturn(true);

    config([
        'database.default' => 'testing',
        'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'session.driver' => 'database',
        'session.connection' => 'testing',
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
    ]);

    Schema::connection('testing')->create('sessions', function ($table): void {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });

    $routing = Mockery::mock(WordPressRoutingService::class);
    $routing->shouldReceive('resolveCondition')->andReturnArg(0);
    $this->app->instance(WordPressRoutingService::class, $routing);

    $provider = new RouteServiceProvider($this->app);
    $provider->register();
    (new ReflectionMethod($provider, 'registerWpMatchMacro'))->invoke($provider);
    (new ReflectionMethod($provider, 'registerWpMacro'))->invoke($provider);

    $this->app['router']->middlewareGroup('web', [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ]);

    // Only the session stack is under test: the WordPress middleware needs a loaded WordPress.
    Route::middleware('web')->group(fn () => Route::wp('is_front_page', fn (): string => 'ok')
        ->withoutMiddleware(RouteServiceProvider::WORDPRESS_MIDDLEWARE));
});

/** @return list<string> */
function setCookieNames(TestResponse $response): array
{
    return array_map(fn ($cookie): string => $cookie->getName(), $response->baseResponse->headers->getCookies());
}

describe('anonymous request on a Route::wp() route', function (): void {
    it('writes a session row and sends cookies by default', function (): void {
        $queries = [];
        DB::connection('testing')->listen(function ($query) use (&$queries): void {
            $queries[] = strtolower(strtok($query->sql, ' '));
        });

        $response = $this->get('/');

        $response->assertOk();
        expect(DB::connection('testing')->table('sessions')->count())->toBe(1)
            ->and($queries)->toContain('insert')
            ->and(setCookieNames($response))->not->toBeEmpty();
    });

    it('writes nothing and sends no cookie when wordpress.sessions is false', function (): void {
        config(['wordpress.sessions' => false]);
        // The macro reads the setting when the route is declared: declare it again.
        $this->app['router']->setRoutes(new RouteCollection);
        Route::middleware('web')->group(fn () => Route::wp('is_front_page', fn (): string => 'ok')
            ->withoutMiddleware(RouteServiceProvider::WORDPRESS_MIDDLEWARE));

        $queries = [];
        DB::connection('testing')->listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->get('/');

        $response->assertOk();
        expect($queries)->toBe([])
            ->and(DB::connection('testing')->table('sessions')->count())->toBe(0)
            ->and(setCookieNames($response))->toBeEmpty()
            ->and($response->headers->has('Set-Cookie'))->toBeFalse();
    });

    it('never starts a session on the WordPress fallback route', function (): void {
        (new ReflectionMethod(new RouteServiceProvider($this->app), 'bootFallbackRoute'))
            ->invoke(new RouteServiceProvider($this->app));

        $fallback = collect(resolve('router')->getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->uri() === '{any}');

        expect(resolve('router')->gatherRouteMiddleware($fallback))
            ->not->toContain(StartSession::class)
            ->not->toContain(PreventRequestForgery::class);
    });
});
