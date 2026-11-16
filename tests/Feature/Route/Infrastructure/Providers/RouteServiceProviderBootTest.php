<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Pollora\Route\Infrastructure\Providers\RouteServiceProvider;
use Pollora\Route\Infrastructure\Services\WordPressRoutingService;
use Pollora\Route\UI\Http\Controllers\FrontendController;

beforeEach(function (): void {
    $routing = Mockery::mock(WordPressRoutingService::class);
    $routing->shouldReceive('resolveCondition')->andReturnArg(0);
    $this->app->instance(WordPressRoutingService::class, $routing);

    $this->provider = new RouteServiceProvider($this->app);
    $this->provider->register();
});

describe('Route::wp() macro', function (): void {
    beforeEach(function (): void {
        (new ReflectionMethod($this->provider, 'registerWpMatchMacro'))->invoke($this->provider);
        (new ReflectionMethod($this->provider, 'registerWpMacro'))->invoke($this->provider);
    });

    it('refuses a route that has a condition but no callback', function (): void {
        expect(fn () => Route::wp('home'))->toThrow(InvalidArgumentException::class, 'requires at least a condition and a callback');
    });

    it('keeps the condition as the URI of a route without condition parameters', function (): void {
        $route = Route::wp('home', fn (): string => '');

        expect($route->uri())->toBe('home')
            ->and($route->getConditionParameters())->toBe([]);
    });

    it('makes a distinct URI per set of condition parameters and keeps the parameters', function (): void {
        $news = Route::wp('category', 'news', fn (): string => '');
        $shop = Route::wp('category', 'shop', fn (): string => '');

        expect($news->uri())->toStartWith('category_')
            ->and($news->uri())->not->toBe($shop->uri())
            ->and($news->getConditionParameters())->toBe(['news'])
            ->and($shop->getConditionParameters())->toBe(['shop']);
    });

    it('limits Route::wpMatch() to the verbs it is given', function (): void {
        $route = Route::wpMatch(['GET'], 'home', fn (): string => '');

        expect($route->methods())->toBe(['GET', 'HEAD']);
    });
});

function fallbackRoutes(): array
{
    return array_values(array_filter(Route::getRoutes()->getRoutes(), fn ($route): bool => $route->uri() === '{any}'));
}

describe('RouteServiceProvider boot', function (): void {
    it('registers the fallback route, answered by WordPress, as soon as the application has booted', function (): void {
        $this->provider->boot();

        $fallback = fallbackRoutes()[0];

        expect($this->app->make('route.fallback.registered'))->toBeTrue()
            ->and($fallback->getAction('controller'))->toBe(FrontendController::class.'@handle')
            ->and($fallback->wheres['any'])->toBe('^(?!api/).*')
            ->and($fallback->isWordPressRoute())->toBeTrue()
            ->and($fallback->gatherMiddleware())->toContain(...RouteServiceProvider::WORDPRESS_MIDDLEWARE);
    });

    it('registers the fallback route when the modules announce their routes', function (): void {
        $this->provider->boot();
        $this->app->forgetInstance('route.fallback.registered');

        expect($this->app->bound('route.fallback.registered'))->toBeFalse();

        Event::dispatch('modules.routes.registered');

        expect($this->app->bound('route.fallback.registered'))->toBeTrue();
    });
});
