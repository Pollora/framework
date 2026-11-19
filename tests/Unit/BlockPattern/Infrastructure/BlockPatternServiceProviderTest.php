<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\BlockPattern\Domain\Contracts\PatternCategoryRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\Infrastructure\Adapters\WordPressPatternDataExtractor;
use Pollora\BlockPattern\Infrastructure\Providers\BlockPatternServiceProvider;
use Pollora\BlockPattern\Infrastructure\Registrars\WordPressPatternCategoryRegistrar;
use Pollora\BlockPattern\Infrastructure\Registrars\WordPressPatternRegistrar;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Infrastructure\Services\LaravelCollectionFactory;
use Pollora\Hook\Domain\Contract\Action;

describe('BlockPatternServiceProvider', function (): void {
    beforeEach(function (): void {
        $this->app = new Container;
        $this->provider = new BlockPatternServiceProvider($this->app);
    });

    it('binds the pattern contracts to their WordPress implementations', function (): void {
        $this->provider->register();

        $this->app->bind(CollectionFactoryInterface::class, LaravelCollectionFactory::class);

        expect($this->app->make(PatternDataExtractorInterface::class))->toBeInstanceOf(WordPressPatternDataExtractor::class)
            ->and($this->app->make(PatternCategoryRegistrarInterface::class))->toBeInstanceOf(WordPressPatternCategoryRegistrar::class)
            ->and($this->app->make(PatternRegistrarInterface::class))->toBeInstanceOf(WordPressPatternRegistrar::class)
            ->and($this->app->bound(PatternServiceInterface::class))->toBeTrue()
            ->and($this->app->isShared(PatternServiceInterface::class))->toBeFalse();
    });

    it('registers the patterns when WordPress runs init', function (): void {
        $hooks = [];
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback) use (&$hooks, $action) {
            $hooks[$hook][] = $callback;

            return $action;
        });
        $this->app->instance(Action::class, $action);

        $registered = 0;
        $service = Mockery::mock(PatternServiceInterface::class);
        $service->shouldReceive('registerAll')->andReturnUsing(function () use (&$registered): void {
            $registered++;
        });
        $this->app->instance(PatternServiceInterface::class, $service);

        $this->provider->boot();

        expect(array_keys($hooks))->toBe(['init'])
            ->and($registered)->toBe(0);

        $hooks['init'][0]();

        expect($registered)->toBe(1);
    });
});
