<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;
use Pollora\Route\Infrastructure\Services\WordPressConditionManager;

describe('ExtendedRouter', function (): void {
    beforeEach(function (): void {
        $this->container = new Container;
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn(null);

        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            if ($key === 'wordpress.conditions') {
                return [
                    'is_single' => 'single',
                    'is_page' => 'page',
                    'is_category' => 'category',
                ];
            }

            if ($key === 'wordpress.plugin_conditions') {
                return [];
            }

            return $default;
        });

        $this->container->instance('config', $config);

        $conditionManager = new WordPressConditionManager($this->container);
        $typeResolver = new WordPressTypeResolver;

        $this->router = new ExtendedRouter($dispatcher, $this->container, $conditionManager, $typeResolver);
    });

    it('creates route objects of correct type', function (): void {
        $route = $this->router->get('/test', fn (): string => 'test');

        expect($route)->toBeInstanceOf(Route::class);
    });

    it('loads WordPress conditions from config', function (): void {
        $conditions = $this->router->getConditions();

        expect($conditions)->toHaveKey('single');
        expect($conditions['single'])->toBe('is_single');
        expect($conditions)->toHaveKey('page');
        expect($conditions['page'])->toBe('is_page');
        expect($conditions)->toHaveKey('category');
        expect($conditions['category'])->toBe('is_category');
    });

    it('resolves condition aliases', function (): void {
        expect($this->router->resolveCondition('single'))->toBe('is_single');
        expect($this->router->resolveCondition('page'))->toBe('is_page');
        expect($this->router->resolveCondition('is_custom'))->toBe('is_custom');
    });

    it('adds WordPress bindings to route', function (): void {
        $route = new Route(['GET'], '/test', fn (WP_Post $post, WP_Query $wp_query): array => [$post, $wp_query]);

        $result = $this->router->addWordPressBindings($route);
        expect($result)->toBe($route);

        $nonWpRoute = new Route(['GET'], '/other', fn (string $name, int $id): array => [$name, $id]);

        $nonWpResult = $this->router->addWordPressBindings($nonWpRoute);
        expect($nonWpResult)->toBe($nonWpRoute);
    });

    it('handles missing config gracefully', function (): void {
        $container = new Container;
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn(null);

        $conditionManager = new WordPressConditionManager($container);
        $typeResolver = new WordPressTypeResolver;

        $router = new ExtendedRouter($dispatcher, $container, $conditionManager, $typeResolver);

        $conditions = $router->getConditions();
        expect($conditions)->toBeArray();
        expect($conditions)->toHaveKey('home');
        expect($conditions['home'])->toBe('is_home');
    });
});
