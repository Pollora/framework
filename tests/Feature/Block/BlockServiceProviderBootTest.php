<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Mockery\MockInterface;
use Pollora\Block\Infrastructure\Providers\BlockServiceProvider;
use Pollora\Block\Infrastructure\Services\EditorRuntime;
use Pollora\Block\Infrastructure\Services\ModuleBlocksRegistrar;
use Pollora\BlockCategory\Domain\Contracts\BlockCategoryServiceInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;

/**
 * boot() hooks the registration of blocks and patterns into WordPress. WordPress
 * itself is not loaded: what is checked is what the provider asks the hooks for
 * and what the hooked callbacks then do.
 */
beforeEach(function (): void {
    $this->actions = [];
    $action = Mockery::mock(Action::class);
    $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback, int $priority = 10) use (&$action): MockInterface {
        $this->actions[] = [$hook, $callback, $priority];

        return $action;
    });
    $this->app->instance(Action::class, $action);

    $this->filters = [];
    $filter = Mockery::mock(Filter::class);
    $filter->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback, int $priority = 10, int $args = 1) use (&$filter): MockInterface {
        $this->filters[] = [$hook, $callback, $priority, $args];

        return $filter;
    });
    $this->app->instance(Filter::class, $filter);

    $this->provider = new BlockServiceProvider($this->app);
    $this->provider->register();

    // After register(), which binds the real services, so these stand in for them.
    $this->categories = Mockery::mock(BlockCategoryServiceInterface::class);
    $this->app->instance(BlockCategoryServiceInterface::class, $this->categories);
});

describe('BlockServiceProvider::boot()', function (): void {
    it('registers the configured block categories while booting', function (): void {
        $registered = 0;
        $this->categories->shouldReceive('registerConfiguredCategories')->andReturnUsing(function () use (&$registered): void {
            $registered++;
        });

        $this->provider->boot();

        expect($registered)->toBe(1);
    });

    it('hooks the block registration and the pattern registration on init', function (): void {
        $this->categories->shouldReceive('registerConfiguredCategories');

        $this->provider->boot();

        expect(array_column($this->actions, 0))->toBe(['init', 'init']);
    });

    it('starts the editor runtime and registers the module blocks when init fires', function (): void {
        $this->categories->shouldReceive('registerConfiguredCategories');
        $calls = [];
        // Both are final: stand-ins that answer to the same calls are bound in their place.
        $this->app->singleton(EditorRuntime::class, function () use (&$calls): object {
            return new class($calls)
            {
                public function __construct(private array &$calls) {}

                public function register(): void
                {
                    $this->calls[] = 'editor runtime';
                }
            };
        });
        $this->app->singleton(ModuleBlocksRegistrar::class, function () use (&$calls): object {
            return new class($calls)
            {
                public function __construct(private array &$calls) {}

                public function registerAll(): void
                {
                    $this->calls[] = 'module blocks';
                }
            };
        });

        $this->provider->boot();
        ($this->actions[0][1])();

        expect($calls)->toBe(['editor runtime', 'module blocks']);
    });

    it('registers the patterns when the second init callback fires', function (): void {
        $this->categories->shouldReceive('registerConfiguredCategories');
        $registered = 0;
        $patterns = Mockery::mock(PatternServiceInterface::class);
        $patterns->shouldReceive('registerAll')->andReturnUsing(function () use (&$registered): void {
            $registered++;
        });
        $this->app->instance(PatternServiceInterface::class, $patterns);

        $this->provider->boot();

        expect($registered)->toBe(0);

        ($this->actions[1][1])();

        expect($registered)->toBe(1);
    });

    it('marks the REST requests of the block renderer as editor previews', function (): void {
        $this->categories->shouldReceive('registerConfiguredCategories');
        $this->provider->boot();

        expect(array_column($this->filters, 0))->toBe(['rest_request_before_callbacks', 'rest_request_after_callbacks'])
            ->and(array_column($this->filters, 3))->toBe([3, 1])
            ->and(array_column($this->filters, 2))->toBe([10, 10]);
    });

    it('offers the stubs of the block command for publication', function (): void {
        $this->categories->shouldReceive('registerConfiguredCategories');

        $this->provider->boot();

        $paths = ServiceProvider::pathsToPublish(BlockServiceProvider::class, 'pollora-block-stubs');

        expect(array_values($paths))->toBe([base_path('stubs/pollora-block')]);
    });
});
