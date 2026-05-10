<?php

declare(strict_types=1);

use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Collection\Infrastructure\Adapters\LaravelCollectionAdapter;
use Pollora\Collection\Infrastructure\Services\LaravelCollectionFactory;

describe('LaravelCollectionFactory', function (): void {
    it('creates a CollectionInterface instance', function (): void {
        $factory = new LaravelCollectionFactory;

        $collection = $factory->make([1, 2, 3]);

        expect($collection)->toBeInstanceOf(CollectionInterface::class);
        expect($collection->all())->toBe([1, 2, 3]);
    });
});

describe('LaravelCollectionAdapter', function (): void {
    it('wraps array into collection', function (): void {
        $collection = new LaravelCollectionAdapter([1, 2, 3]);

        expect($collection->all())->toBe([1, 2, 3]);
        expect($collection->count())->toBe(3);
    });

    it('wraps Laravel Collection instance', function (): void {
        $laravel = collect(['a', 'b']);
        $collection = new LaravelCollectionAdapter($laravel);

        expect($collection->all())->toBe(['a', 'b']);
    });

    it('maps items and returns new CollectionInterface', function (): void {
        $collection = new LaravelCollectionAdapter([1, 2, 3]);

        $mapped = $collection->map(fn ($item) => $item * 2);

        expect($mapped)->toBeInstanceOf(CollectionInterface::class);
        expect($mapped->all())->toBe([2, 4, 6]);
    });

    it('filters items and returns new CollectionInterface', function (): void {
        $collection = new LaravelCollectionAdapter([1, 2, 3, 4]);

        $filtered = $collection->filter(fn ($item) => $item > 2);

        expect($filtered)->toBeInstanceOf(CollectionInterface::class);
        expect($filtered->values()->all())->toBe([3, 4]);
    });

    it('detects empty collection', function (): void {
        expect((new LaravelCollectionAdapter([]))->isEmpty())->toBeTrue();
        expect((new LaravelCollectionAdapter([1]))->isEmpty())->toBeFalse();
    });

    it('merges items', function (): void {
        $collection = new LaravelCollectionAdapter([1, 2]);

        $merged = $collection->merge([3, 4]);

        expect($merged->all())->toBe([1, 2, 3, 4]);
    });

    it('exposes underlying Laravel Collection', function (): void {
        $collection = new LaravelCollectionAdapter([1, 2]);

        expect($collection->getLaravelCollection())->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('supports array access', function (): void {
        $collection = new LaravelCollectionAdapter(['a', 'b', 'c']);

        expect($collection[0])->toBe('a');
        expect($collection[2])->toBe('c');
        expect(isset($collection[1]))->toBeTrue();
        expect(isset($collection[5]))->toBeFalse();
    });

    it('is countable and iterable', function (): void {
        $collection = new LaravelCollectionAdapter([10, 20, 30]);

        expect($collection)->toHaveCount(3);

        $items = [];
        foreach ($collection as $item) {
            $items[] = $item;
        }
        expect($items)->toBe([10, 20, 30]);
    });
});
