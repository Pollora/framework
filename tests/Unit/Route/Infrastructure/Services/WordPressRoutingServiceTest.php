<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Mockery as m;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Pollora\Route\Infrastructure\Services\WordPressRoutingService;

beforeEach(function (): void {
    $this->conditionManager = m::mock(WordPressConditionManagerInterface::class);
    $this->typeResolver = m::mock(WordPressTypeResolverInterface::class);
    $this->service = new WordPressRoutingService(
        $this->conditionManager,
        $this->typeResolver,
    );

    // Mock WordPress classes
    if (! class_exists('WP_Post')) {
        eval('namespace { class WP_Post { public int $ID = 1; } }');
    }
});

describe('WordPressRoutingService', function (): void {

    describe('condition resolution', function (): void {
        it('resolves condition aliases via manager', function (): void {
            $this->conditionManager->shouldReceive('resolveCondition')
                ->with('page')
                ->andReturn('is_page');

            expect($this->service->resolveCondition('page'))->toBe('is_page');
        });

        it('passes through unknown conditions', function (): void {
            $this->conditionManager->shouldReceive('resolveCondition')
                ->with('unknown')
                ->andReturn('unknown');

            expect($this->service->resolveCondition('unknown'))->toBe('unknown');
        });

        it('returns all conditions', function (): void {
            $this->conditionManager->shouldReceive('getConditions')
                ->andReturn(['page' => 'is_page', 'single' => 'is_single']);

            $conditions = $this->service->getConditions();
            expect($conditions)->toHaveKey('page');
            expect($conditions['page'])->toBe('is_page');
        });
    });

    describe('WordPress type registration', function (): void {
        it('registers all five WordPress types in container', function (): void {
            $container = new Container;

            $this->typeResolver->shouldReceive('resolvePost')->andReturn(new WP_Post);
            $this->typeResolver->shouldReceive('resolveTerm')->andReturn(null);
            $this->typeResolver->shouldReceive('resolveUser')->andReturn(null);
            $this->typeResolver->shouldReceive('resolveQuery')->andReturn(null);
            $this->typeResolver->shouldReceive('resolveWP')->andReturn(null);

            $this->service->registerWordPressTypes($container);

            expect($container->bound('WP_Post'))->toBeTrue();
            expect($container->bound('WP_Term'))->toBeTrue();
            expect($container->bound('WP_User'))->toBeTrue();
            expect($container->bound('WP_Query'))->toBeTrue();
            expect($container->bound('WP'))->toBeTrue();
        });

        it('resolves types safely without throwing', function (): void {
            $container = new Container;

            $this->typeResolver->shouldReceive('resolvePost')->andThrow(new RuntimeException('WP not loaded'));
            $this->typeResolver->shouldReceive('resolveTerm')->andReturn(null);
            $this->typeResolver->shouldReceive('resolveUser')->andReturn(null);
            $this->typeResolver->shouldReceive('resolveQuery')->andReturn(null);
            $this->typeResolver->shouldReceive('resolveWP')->andReturn(null);

            $this->service->registerWordPressTypes($container);

            // Should not throw, resolver wrapped safely
            $result = $container->make('WP_Post');
            expect($result)->toBeNull();
        });
    });

    describe('WordPress parameter binding', function (): void {
        it('binds WordPress parameters to route via type-hints', function (): void {
            $post = new WP_Post;
            $this->typeResolver->shouldReceive('resolve')
                ->with('WP_Post')
                ->andReturn($post);

            $route = new Route(['GET'], '/test', fn (WP_Post $post): string => 'test');
            $route->bind(Request::create('/test'));

            $this->service->bindWordPressParameters($route);

            expect($route->parameter('post'))->toBe($post);
        });

        it('skips builtin types', function (): void {
            $this->typeResolver->shouldNotReceive('resolve');

            $route = new Route(['GET'], '/test', fn (string $name): string => 'test');
            $route->bind(Request::create('/test'));

            $this->service->bindWordPressParameters($route);

            expect($route->parameter('name'))->toBeNull();
        });

        it('handles exceptions gracefully', function (): void {
            $this->typeResolver->shouldReceive('resolve')
                ->andThrow(new RuntimeException('WP not loaded'));

            $route = new Route(['GET'], '/test', fn (WP_Post $post): string => 'test');
            $route->bind(Request::create('/test'));

            $this->service->bindWordPressParameters($route);

            expect($route->parameter('post'))->toBeNull();
        });
    });
});
