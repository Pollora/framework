<?php

declare(strict_types=1);

use Illuminate\Cache\Repository;
use Pollora\ObjectCache\Infrastructure\Stores\InMemoryCacheStore;
use Pollora\RedisObjectCache\Infrastructure\Cache\ObjectCacheStore;
use Tests\Support\RedisObjectCache\Caches;
use Tests\Support\RedisObjectCache\FrozenClock;
use Tests\Support\RedisObjectCache\UnavailableStore;

function laravelStore(?InMemoryCacheStore $persistent = null, string $group = 'laravel'): ObjectCacheStore
{
    return new ObjectCacheStore(Caches::make($persistent ?? new InMemoryCacheStore), $group);
}

it('puts and gets a value, and misses as null', function (): void {
    $store = laravelStore();

    expect($store->get('a'))->toBeNull()
        ->and($store->put('a', ['x' => 1], 60))->toBeTrue()
        ->and($store->get('a'))->toBe(['x' => 1]);
});

it('reads a stored false and a stored null as values, not misses', function (): void {
    $store = laravelStore();
    $store->put('f', false, 60);

    expect($store->get('f'))->toBeFalse();
});

it('reads several keys, null for a missing one', function (): void {
    $store = laravelStore();
    $store->putMany(['a' => 1, 'b' => 2], 60);

    expect($store->many(['a', 'b', 'c']))->toBe(['a' => 1, 'b' => 2, 'c' => null]);
});

it('removes an entry instead of storing it for zero seconds or less', function (): void {
    $store = laravelStore();
    $store->put('a', 1, 60);

    expect($store->put('a', 2, 0))->toBeTrue()
        ->and($store->get('a'))->toBeNull();
});

it('adds only when the key is free', function (): void {
    $store = laravelStore();

    expect($store->add('lock', 1, 60))->toBeTrue()
        ->and($store->add('lock', 2, 60))->toBeFalse()
        ->and($store->get('lock'))->toBe(1)
        ->and($store->add('never', 1, 0))->toBeFalse();
});

it('increments and decrements, false for a missing key', function (): void {
    $store = laravelStore();

    expect($store->increment('n'))->toBeFalse();

    $store->put('n', 5, 60);

    expect($store->increment('n', 3))->toBe(8)
        ->and($store->decrement('n', 2))->toBe(6);
});

it('keeps a value forever', function (): void {
    $store = laravelStore();

    expect($store->forever('k', 'v'))->toBeTrue()
        ->and($store->get('k'))->toBe('v');
});

it('touches an existing entry and refuses a missing one', function (): void {
    $clock = new FrozenClock(1000);
    $persistent = new InMemoryCacheStore($clock);
    $store = laravelStore($persistent);
    $store->put('k', 'v', 10);

    expect($store->touch('k', 100))->toBeTrue();

    $clock->advance(50);

    expect($store->get('k'))->toBe('v')
        ->and($store->touch('missing', 10))->toBeFalse();
});

it('forgets one entry', function (): void {
    $store = laravelStore();
    $store->put('k', 1, 60);

    expect($store->forget('k'))->toBeTrue()
        ->and($store->get('k'))->toBeNull();
});

it('flushes its own group only', function (): void {
    $persistent = new InMemoryCacheStore;
    $cache = Caches::make($persistent);
    $store = new ObjectCacheStore($cache, 'laravel');
    $store->put('a', 1, 60);

    $cache->set('post', 'p', 'posts');

    expect($store->flush())->toBeTrue()
        ->and($store->get('a'))->toBeNull()
        ->and($cache->get('post', 'posts', true)?->value)->toBe('p');
});

it('keeps two groups apart and shares keys with the object cache', function (): void {
    $cache = Caches::make(new InMemoryCacheStore);
    $one = new ObjectCacheStore($cache, 'one');
    $two = new ObjectCacheStore($cache, 'two');
    $one->put('k', 'from-one', 60);

    expect($two->get('k'))->toBeNull()
        ->and($cache->get('k', 'one', true)?->value)->toBe('from-one');
});

it('has no key prefix of its own', function (): void {
    expect(laravelStore()->getPrefix())->toBe('');
});

it('degrades without throwing when the store is unavailable', function (): void {
    $store = new ObjectCacheStore(Caches::make(new UnavailableStore), 'laravel');

    expect($store->get('a'))->toBeNull()
        ->and($store->flush())->toBeTrue();
});

it('works under a Laravel repository', function (): void {
    $cache = new Repository(laravelStore());

    expect($cache->remember('k', 60, fn (): string => 'computed'))->toBe('computed')
        ->and($cache->get('k'))->toBe('computed')
        ->and($cache->add('k', 'other', 60))->toBeFalse();
});

it('reads what another process wrote, not what this request cached', function (): void {
    $persistent = new InMemoryCacheStore;
    $store = new ObjectCacheStore(Caches::make($persistent), 'laravel');
    $store->put('a', 'mine', 60);
    $store->put('b', 'mine', 60);

    $other = Caches::make($persistent);
    $other->set('a', 'theirs', 'laravel');
    $other->set('b', 'theirs', 'laravel');

    expect($store->get('a'))->toBe('theirs')
        ->and($store->many(['b']))->toBe(['b' => 'theirs']);
});

it('reads a missing key as null without a warning', function (): void {
    $store = laravelStore();
    $store->put('a', 1, 60);

    set_error_handler(static function (int $level, string $message): never {
        throw new ErrorException($message, 0, $level);
    });

    try {
        $values = $store->many(['a', 'missing']);
    } finally {
        restore_error_handler();
    }

    expect($values)->toBe(['a' => 1, 'missing' => null]);
});

it('stores for one second, not for none', function (): void {
    $store = laravelStore();

    expect($store->put('k', 'v', 1))->toBeTrue()
        ->and($store->get('k'))->toBe('v');
});

it('reports a failed write of several entries, and a successful one', function (): void {
    $store = laravelStore();

    expect($store->putMany(['a' => 1, 'b' => 2], 60))->toBeTrue()
        ->and($store->putMany(['' => 1, 'c' => 2], 60))->toBeFalse()
        ->and($store->get('c'))->toBe(2);
});

it('keeps a value forever, past any lifetime', function (): void {
    $clock = new FrozenClock;
    $store = new ObjectCacheStore(Caches::make(new InMemoryCacheStore($clock)), 'laravel');
    $store->forever('k', 'v');

    $clock->advance(100000);

    expect($store->get('k'))->toBe('v');
});

it('gives a touched entry the lifetime asked for, counted from now', function (): void {
    $clock = new FrozenClock;
    $store = new ObjectCacheStore(Caches::make(new InMemoryCacheStore($clock)), 'laravel');
    $store->forever('k', 'v');
    $store->touch('k', 100);

    $clock->advance(99);
    $stillThere = $store->get('k');
    $clock->advance(2);

    expect($stillThere)->toBe('v')
        ->and($store->get('k'))->toBeNull();
});

it('lets a touch with no lifetime keep the entry', function (): void {
    $clock = new FrozenClock;
    $store = new ObjectCacheStore(Caches::make(new InMemoryCacheStore($clock)), 'laravel');
    $store->put('k', 'v', 10);
    $store->touch('k', -5);

    $clock->advance(1000);

    expect($store->get('k'))->toBe('v');
});

it('does not touch an entry another process deleted', function (): void {
    $persistent = new InMemoryCacheStore;
    $store = new ObjectCacheStore(Caches::make($persistent), 'laravel');
    $store->put('k', 'v', 60);
    Caches::make($persistent)->delete('k', 'laravel');

    expect($store->touch('k', 60))->toBeFalse();
});

it('adds for one second, not for none', function (): void {
    $store = laravelStore();

    expect($store->add('k', 'v', 1))->toBeTrue()
        ->and($store->get('k'))->toBe('v');
});
