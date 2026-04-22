<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

require_once dirname(__DIR__, 3).'/helpers.php';

describe('WordPressRouteResolution', function (): void {
    beforeEach(function (): void {
        setupWordPressMocks();

        $this->container = new Container;
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn(null);

        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            if ($key === 'wordpress.conditions') {
                return [
                    'is_front_page' => 'front',
                    'is_home' => 'home',
                    'is_page' => 'page',
                    'is_single' => 'single',
                    'is_author' => 'author',
                    'is_category' => 'archive',
                    'is_page_template' => 'template',
                    'is_404' => ['404', 'not_found'],
                ];
            }

            if ($key === 'wordpress.plugin_conditions') {
                return [];
            }

            return $default;
        });

        $this->container->instance('config', $config);
        $this->router = new ExtendedRouter($dispatcher, $this->container);
    });

    afterEach(function (): void {
        resetWordPressMocks();
    });

    it('resolves condition aliases correctly', function (): void {
        expect($this->router->resolveCondition('front'))->toBe('is_front_page');
        expect($this->router->resolveCondition('home'))->toBe('is_home');
        expect($this->router->resolveCondition('page'))->toBe('is_page');
        expect($this->router->resolveCondition('single'))->toBe('is_single');
        expect($this->router->resolveCondition('author'))->toBe('is_author');
        expect($this->router->resolveCondition('archive'))->toBe('is_category');
        expect($this->router->resolveCondition('template'))->toBe('is_page_template');
        expect($this->router->resolveCondition('404'))->toBe('is_404');
        expect($this->router->resolveCondition('is_singular'))->toBe('is_singular');
    });

    it('marks WordPress route correctly', function (): void {
        $wpRoute = createWpRoute($this->router, 'front');
        $laravelRoute = new Route(['GET'], '/test', fn (): string => 'test');

        expect($wpRoute->isWordPressRoute())->toBeTrue();
        expect($laravelRoute->isWordPressRoute())->toBeFalse();
    });

    it('has correct condition on WordPress route', function (): void {
        $route = createWpRoute($this->router, 'front');

        expect($route->getCondition())->toBe('is_front_page');
        expect($route->hasCondition())->toBeTrue();
    });

    it('supports route with parameters', function (): void {
        $route = createWpRoute($this->router, 'is_singular', ['realisations']);

        expect($route->getCondition())->toBe('is_singular');
        expect($route->getConditionParameters())->toBe(['realisations']);
    });

    it('has correct methods and properties', function (): void {
        $route = createWpRoute($this->router, 'front');

        expect($route->methods())->toBe(['GET', 'HEAD']);
        expect($route->uri())->toBe('front');
        expect($route->isWordPressRoute())->toBeTrue();
        expect($route->hasCondition())->toBeTrue();
        expect($route->getCondition())->toBe('is_front_page');
        expect($route->getConditionParameters())->toBe([]);
    });

    it('resolves all web route conditions correctly', function (): void {
        $webRoutes = [
            'front' => 'is_front_page',
            'is_singular' => 'is_singular',
            'home' => 'is_home',
            'template' => 'is_page_template',
            'single' => 'is_single',
            'page' => 'is_page',
            'author' => 'is_author',
            'archive' => 'is_category',
        ];

        foreach ($webRoutes as $alias => $expectedCondition) {
            $route = createWpRoute($this->router, $alias);
            expect($route->getCondition())->toBe($expectedCondition, sprintf("Route alias '%s' should resolve to '%s'", $alias, $expectedCondition));
        }
    });

    it('resolves 404 route condition', function (): void {
        expect($this->router->resolveCondition('404'))->toBe('is_404');

        $route = createWpRoute($this->router, '404');
        expect($route->getCondition())->toBe('is_404');
        expect($route->isWordPressRoute())->toBeTrue();
    });

    it('matches routes with WordPress condition mocking', function (): void {
        setWordPressFunction('is_front_page', fn (): true => true);
        $frontRoute = createWpRoute($this->router, 'front');
        $request = Request::create('/', 'GET');
        expect($frontRoute->matches($request))->toBeTrue();

        setWordPressFunction('is_front_page', fn (): false => false);
        expect($frontRoute->matches($request))->toBeFalse();

        setWordPressFunction('is_single', fn (): true => true);
        $singleRoute = createWpRoute($this->router, 'single');
        expect($singleRoute->matches(Request::create('/blog/article', 'GET')))->toBeTrue();

        setWordPressFunction('is_category', fn (): true => true);
        $categoryRoute = createWpRoute($this->router, 'archive');
        expect($categoryRoute->matches(Request::create('/category/news', 'GET')))->toBeTrue();
    });

    it('matches route with parameters using WordPress mocks', function (): void {
        $route = createWpRoute($this->router, 'is_singular', ['realisations']);

        setWordPressFunction('is_singular', fn (): true => true);
        expect($route->matches(Request::create('/realisations/campus-vert', 'GET')))->toBeTrue();

        setWordPressFunction('is_singular', fn (): false => false);
        expect($route->matches(Request::create('/realisations/campus-vert', 'GET')))->toBeFalse();
    });

    it('simulates multiple conditions correctly', function (): void {
        setWordPressConditions([
            'is_front_page' => true, 'is_home' => false, 'is_page' => false,
            'is_single' => false, 'is_category' => false, 'is_404' => false,
        ]);

        $frontRoute = createWpRoute($this->router, 'front');
        $homeRoute = createWpRoute($this->router, 'home');
        $request = Request::create('/', 'GET');

        expect($frontRoute->matches($request))->toBeTrue();
        expect($homeRoute->matches($request))->toBeFalse();

        setWordPressConditions([
            'is_front_page' => false, 'is_home' => true, 'is_page' => false,
            'is_single' => false, 'is_category' => false, 'is_404' => false,
        ]);

        expect($frontRoute->matches(Request::create('/blog', 'GET')))->toBeFalse();
        expect($homeRoute->matches(Request::create('/blog', 'GET')))->toBeTrue();

        setWordPressConditions([
            'is_front_page' => false, 'is_home' => false, 'is_page' => false,
            'is_single' => false, 'is_category' => true, 'is_404' => false,
        ]);

        $categoryRoute = createWpRoute($this->router, 'archive');
        expect($categoryRoute->matches(Request::create('/blog/category/actus', 'GET')))->toBeTrue();
    });
});

function createWpRoute(ExtendedRouter $router, string $condition, array $parameters = []): Route
{
    $resolvedCondition = $router->resolveCondition($condition);
    $route = new Route(['GET'], $condition, fn (): string => 'matched');
    $route->setIsWordPressRoute(true);
    $route->setCondition($resolvedCondition);
    $route->setConditionParameters($parameters);

    return $route;
}
