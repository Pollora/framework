<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Pollora\Route\Infrastructure\Listeners\ApplyApplicationRouteContext;
use Pollora\Route\Infrastructure\Models\Route;

describe('ApplyApplicationRouteContext listener', function (): void {
    beforeEach(function (): void {
        $this->filters = [];
        Functions\when('add_filter')->alias(function (string $hook, Closure $callback): void {
            $this->filters[$hook] = $callback;
        });

        $GLOBALS['wp_query'] = (object) ['is_404' => true];
    });

    afterEach(function (): void {
        unset($GLOBALS['wp_query']);
    });

    /** A route matched against a request, as the router hands it to RouteMatched. */
    function matchedRoute(string $uri, string $path, bool $wordpress = false): RouteMatched
    {
        $request = Request::create($path);
        $route = (new Route(['GET'], $uri, fn (): string => ''))->setIsWordPressRoute($wordpress);
        $route->bind($request);

        return new RouteMatched($route, $request);
    }

    it('clears the 404 WordPress gave a URL only Laravel knows', function (): void {
        (new ApplyApplicationRouteContext)->handle(matchedRoute('dashboard', '/dashboard'));

        expect($GLOBALS['wp_query']->is_404)->toBeFalse();
    });

    it('names the body after the route and its parameters, and keeps WordPress classes', function (): void {
        (new ApplyApplicationRouteContext)->handle(matchedRoute('dashboard/{tab}', '/dashboard/settings'));

        expect(($this->filters['body_class'])(['wp-theme-buzz']))
            ->toBe(['dashboard', 'tab-settings', 'wp-theme-buzz']);
    });

    it('leaves a WordPress route alone, so a real 404 keeps error404', function (): void {
        // Route::wp() routes and the template-hierarchy fallback are both flagged.
        (new ApplyApplicationRouteContext)->handle(matchedRoute('{any}', '/no-such-page', wordpress: true));

        expect($GLOBALS['wp_query']->is_404)->toBeTrue()
            ->and($this->filters)->toBe([]);
    });

    it('ignores a route that is not a Pollora route', function (): void {
        $request = Request::create('/plain');
        $route = new Illuminate\Routing\Route(['GET'], 'plain', fn (): string => '');

        (new ApplyApplicationRouteContext)->handle(new RouteMatched($route, $request));

        expect($GLOBALS['wp_query']->is_404)->toBeTrue()
            ->and($this->filters)->toBe([]);
    });
});
