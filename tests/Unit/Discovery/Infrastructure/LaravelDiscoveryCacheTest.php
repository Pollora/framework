<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Models\DiscoveryItems;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Adapters\LaravelDiscoveryCache;

function covDiscoveredItems(): DiscoveryItems
{
    $items = new DiscoveryItems;
    $items->add(new DiscoveryLocation('App\\', __DIR__), ['class' => 'App\\Foo']);

    return $items;
}

describe('LaravelDiscoveryCache', function (): void {
    beforeEach(function (): void {
        $this->store = new Repository(new ArrayStore);
        $this->cache = new LaravelDiscoveryCache($this->store);
    });

    it('gives back the discovered items it was given', function (): void {
        $items = covDiscoveredItems();

        expect($this->cache->put('hooks', $items))->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeTrue()
            ->and($this->cache->get('hooks')->__serialize())->toBe($items->__serialize());
    });

    it('has nothing for a key that was never stored', function (): void {
        expect($this->cache->has('hooks'))->toBeFalse()
            ->and($this->cache->get('hooks'))->toBeNull();
    });

    it('prefixes the keys it writes so they cannot collide with other cache users', function (): void {
        $this->cache->put('hooks', covDiscoveredItems());

        expect($this->store->has('pollora.discovery.hooks'))->toBeTrue()
            ->and($this->store->has('hooks'))->toBeFalse();
    });

    it('uses the prefix it is configured with', function (): void {
        $custom = new LaravelDiscoveryCache($this->store, 'custom.');
        $custom->put('hooks', covDiscoveredItems());

        expect($this->store->has('custom.hooks'))->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeFalse();
    });

    it('treats a cached value that is not an array as a miss', function (): void {
        $this->store->put('pollora.discovery.hooks', 'corrupted', 60);

        expect($this->cache->get('hooks'))->toBeNull();
    });

    it('forgets a single key and leaves the others', function (): void {
        $this->cache->put('hooks', covDiscoveredItems());
        $this->cache->put('blocks', covDiscoveredItems());

        expect($this->cache->forget('hooks'))->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeFalse()
            ->and($this->cache->has('blocks'))->toBeTrue();
    });

    it('empties the whole cache on flush', function (): void {
        $this->cache->put('hooks', covDiscoveredItems());
        $this->cache->put('blocks', covDiscoveredItems());

        expect($this->cache->flush())->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeFalse()
            ->and($this->cache->has('blocks'))->toBeFalse();
    });

    it('stores items for the ttl it is given', function (): void {
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('put')->once()->with('pollora.discovery.hooks', Mockery::type('array'), 120)->andReturn(true);
        $store->shouldReceive('put')->once()->with('pollora.discovery.blocks', Mockery::type('array'), 86400)->andReturn(true);

        $cache = new LaravelDiscoveryCache($store);

        expect($cache->put('hooks', covDiscoveredItems(), 120))->toBeTrue()
            ->and($cache->put('blocks', covDiscoveredItems()))->toBeTrue();
    });

    it('uses the default ttl it is configured with', function (): void {
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('put')->once()->with('x.hooks', Mockery::type('array'), 30)->andReturn(true);

        expect((new LaravelDiscoveryCache($store, 'x.', 30))->put('hooks', covDiscoveredItems()))->toBeTrue();
    });

    it('builds the same key whatever the order of the locations', function (): void {
        $a = new DiscoveryLocation('A\\', '/tmp/aaa');
        $b = new DiscoveryLocation('B\\', '/tmp/bbb');

        expect($this->cache->generateKey('hooks', [$a, $b]))->toBe($this->cache->generateKey('hooks', [$b, $a]))
            ->and($this->cache->generateKey('hooks', [$a, $b]))->toStartWith('hooks.');
    });

    it('builds a different key when the discovery or the locations differ', function (): void {
        $a = new DiscoveryLocation('A\\', '/tmp/aaa');
        $b = new DiscoveryLocation('B\\', '/tmp/bbb');

        expect($this->cache->generateKey('hooks', [$a]))->not->toBe($this->cache->generateKey('hooks', [$b]))
            ->and($this->cache->generateKey('hooks', [$a]))->not->toBe($this->cache->generateKey('blocks', [$a]));
    });

    it('gives anything that is not a location the same placeholder path', function (): void {
        $location = Mockery::mock(DiscoveryLocationInterface::class);
        $location->shouldReceive('getPath')->andReturn('unknown');

        expect($this->cache->generateKey('hooks', ['not a location']))->toBe($this->cache->generateKey('hooks', [$location]));
    });
});
