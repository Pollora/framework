<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Pollora\BlockPattern\Application\Services\PatternService;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\Domain\Models\Pattern;
use Pollora\BlockPattern\Infrastructure\Registrars\PatternCategoryRegistrar;
use Pollora\BlockPattern\Infrastructure\Registrars\WordPressPatternCategoryRegistrar;
use Pollora\BlockPattern\Infrastructure\Registrars\WordPressPatternRegistrar;
use Pollora\BlockPattern\UI\PatternComponent;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;

describe('pattern registrars', function (): void {
    it('registers a pattern with WordPress under its slug', function (): void {
        $registered = [];
        Functions\when('register_block_pattern')->alias(function (string $slug, array $properties) use (&$registered): bool {
            $registered[] = [$slug, $properties];

            return true;
        });

        (new WordPressPatternRegistrar)->registerPattern(new Pattern('theme/hero', 'Hero', '<p>Hero</p>', categories: ['featured']));

        expect($registered)->toBe([['theme/hero', ['slug' => 'theme/hero', 'title' => 'Hero', 'content' => '<p>Hero</p>', 'categories' => ['featured']]]]);
    });

    it('registers a pattern category with WordPress', function (): void {
        $registered = [];
        Functions\when('register_block_pattern_category')->alias(function (string $slug, array $attributes) use (&$registered): bool {
            $registered[] = [$slug, $attributes];

            return true;
        });

        (new WordPressPatternCategoryRegistrar)->registerCategory('featured', ['label' => 'Featured']);

        expect($registered)->toBe([['featured', ['label' => 'Featured']]]);
    });

    it('registers every category of the theme configuration', function (): void {
        $registered = [];
        Functions\when('register_block_pattern_category')->alias(function (string $slug, array $attributes) use (&$registered): bool {
            $registered[] = $slug;

            return true;
        });
        $config = Mockery::mock(ConfigRepositoryInterface::class);
        $config->shouldReceive('get')->with('theme.gutenberg.categories.patterns', [])->andReturn([
            'featured' => ['label' => 'Featured'],
            'heroes' => ['label' => 'Heroes'],
        ]);

        (new PatternCategoryRegistrar($config))->register();

        expect($registered)->toBe(['featured', 'heroes']);
    });

    it('registers nothing when the configuration holds no usable category list', function (mixed $configured): void {
        $registered = [];
        Functions\when('register_block_pattern_category')->alias(function (string $slug) use (&$registered): bool {
            $registered[] = $slug;

            return true;
        });
        $config = Mockery::mock(ConfigRepositoryInterface::class);
        $config->shouldReceive('get')->andReturn($configured);

        (new PatternCategoryRegistrar($config))->register();

        expect($registered)->toBe([]);
    })->with(['empty' => [[]], 'not an array' => ['featured']]);
});

describe('PatternComponent', function (): void {
    it('registers the patterns when WordPress runs init', function (): void {
        $service = Mockery::mock(PatternServiceInterface::class);
        $service->shouldReceive('registerAll')->once();

        $hooks = [];
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback) use (&$hooks, $action) {
            $hooks[$hook] = $callback;

            return $action;
        });

        $container = new class extends Container
        {
            public function get(string $id): mixed
            {
                return $this->make($id);
            }
        };
        $container->instance(PatternServiceInterface::class, $service);
        $container->instance(Action::class, $action);

        (new PatternComponent($container))->register();

        expect(array_keys($hooks))->toBe(['init']);

        $hooks['init']();
    });
});
