<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\BlockCategory\Infrastructure\Registrars\BlockCategoryRegistrar;
use Pollora\Collection\Infrastructure\Adapters\LaravelCollectionAdapter;

describe('BlockCategoryRegistrar', function (): void {
    it('adds nothing to the editor when there is no category', function (): void {
        Functions\when('add_filter')->alias(fn (): never => throw new LogicException('no filter expected'));

        (new BlockCategoryRegistrar)->registerCategories(new LaravelCollectionAdapter([]));

        expect(true)->toBeTrue();
    });

    it('appends the categories after the ones WordPress already has', function (): void {
        $hooks = [];
        Functions\when('add_filter')->alias(function (string $hook, Closure $added) use (&$hooks): bool {
            $hooks[$hook] = $added;

            return true;
        });

        (new BlockCategoryRegistrar)->registerCategories(new LaravelCollectionAdapter(['news' => ['slug' => 'news', 'title' => 'News']]));

        expect($hooks)->toHaveKey('block_categories_all')
            ->and($hooks['block_categories_all']([['slug' => 'text', 'title' => 'Text']]))->toBe([
            ['slug' => 'text', 'title' => 'Text'],
            ['slug' => 'news', 'title' => 'News'],
        ]);
    });
});
