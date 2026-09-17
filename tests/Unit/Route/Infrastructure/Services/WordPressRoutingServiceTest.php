<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Mockery as m;
use Pollora\Route\Application\UseCases\BindWordPressParametersUseCase;
use Pollora\Route\Application\UseCases\RegisterWordPressTypesUseCase;
use Pollora\Route\Infrastructure\Models\Route;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;
use Pollora\Route\Infrastructure\Services\WordPressRoutingService;

beforeEach(function (): void {
    $this->conditionManager = m::mock(WordPressConditionManagerInterface::class);
    $this->registerTypesUseCase = m::mock(RegisterWordPressTypesUseCase::class);
    $this->bindParametersUseCase = m::mock(BindWordPressParametersUseCase::class);

    $this->service = new WordPressRoutingService(
        $this->conditionManager,
        $this->registerTypesUseCase,
        $this->bindParametersUseCase,
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
        it('delegates to RegisterWordPressTypesUseCase', function (): void {
            $container = new Container;

            $this->registerTypesUseCase->shouldReceive('execute')
                ->once()
                ->with($container);

            $this->service->registerWordPressTypes($container);
        });
    });

    describe('WordPress parameter binding', function (): void {
        it('delegates to BindWordPressParametersUseCase', function (): void {
            $route = new Route(['GET'], '/test', fn (): string => 'test');
            $route->bind(Request::create('/test'));

            $this->bindParametersUseCase->shouldReceive('execute')
                ->once()
                ->with($route);

            $this->service->bindWordPressParameters($route);
        });
    });
});
