<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Models\DiscoveryItems;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Adapters\LaravelDiscoveryCache;

function contentDiscoveredItems(): DiscoveryItems
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
        $items = contentDiscoveredItems();

        expect($this->cache->put('hooks', $items))->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeTrue()
            ->and($this->cache->get('hooks')->__serialize())->toBe($items->__serialize());
    });

    it('has nothing for a key that was never stored', function (): void {
        expect($this->cache->has('hooks'))->toBeFalse()
            ->and($this->cache->get('hooks'))->toBeNull();
    });

    it('prefixes the keys it writes so they cannot collide with other cache users', function (): void {
        $this->cache->put('hooks', contentDiscoveredItems());

        expect($this->store->has('pollora.discovery.hooks'))->toBeTrue()
            ->and($this->store->has('hooks'))->toBeFalse();
    });

    it('uses the prefix it is configured with', function (): void {
        $custom = new LaravelDiscoveryCache($this->store, 'custom.');
        $custom->put('hooks', contentDiscoveredItems());

        expect($this->store->has('custom.hooks'))->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeFalse();
    });

    it('treats a cached value that is not an array as a miss', function (): void {
        $this->store->put('pollora.discovery.hooks', 'corrupted', 60);

        expect($this->cache->get('hooks'))->toBeNull();
    });

    it('forgets a single key and leaves the others', function (): void {
        $this->cache->put('hooks', contentDiscoveredItems());
        $this->cache->put('blocks', contentDiscoveredItems());

        expect($this->cache->forget('hooks'))->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeFalse()
            ->and($this->cache->has('blocks'))->toBeTrue();
    });

    it('empties the keys it wrote on flush', function (): void {
        $this->cache->put('hooks', contentDiscoveredItems());
        $this->cache->put('blocks', contentDiscoveredItems());

        expect($this->cache->flush())->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeFalse()
            ->and($this->cache->has('blocks'))->toBeFalse();
    });

    it('leaves the keys of other cache users alone on flush', function (): void {
        $this->store->put('app.settings', 'kept', 60);
        $this->store->forever('pollora.other', 'kept');

        $this->cache->put('hooks', contentDiscoveredItems());

        $this->cache->flush();

        expect($this->store->get('app.settings'))->toBe('kept')
            ->and($this->store->get('pollora.other'))->toBe('kept')
            ->and($this->cache->has('hooks'))->toBeFalse();
    });

    it('flushes what another instance with the same prefix wrote', function (): void {
        (new LaravelDiscoveryCache($this->store))->put('hooks', contentDiscoveredItems());

        $this->cache->flush();

        expect($this->cache->has('hooks'))->toBeFalse();
    });

    it('flushes only its own prefix', function (): void {
        $custom = new LaravelDiscoveryCache($this->store, 'custom.');
        $custom->put('hooks', contentDiscoveredItems());

        $this->cache->put('hooks', contentDiscoveredItems());

        $this->cache->flush();

        expect($custom->has('hooks'))->toBeTrue()
            ->and($this->cache->has('hooks'))->toBeFalse();
    });

    it('can flush before anything was written', function (): void {
        expect($this->cache->flush())->toBeTrue();
    });

    it('stores items for the ttl it is given', function (): void {
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('get')->with('pollora.discovery.__index__', [])->andReturn([]);
        $store->shouldReceive('forever')->with('pollora.discovery.__index__', Mockery::type('array'))->andReturn(true);
        $store->shouldReceive('put')->once()->with('pollora.discovery.hooks', Mockery::type('array'), 120)->andReturn(true);
        $store->shouldReceive('put')->once()->with('pollora.discovery.blocks', Mockery::type('array'), 86400)->andReturn(true);

        $cache = new LaravelDiscoveryCache($store);

        expect($cache->put('hooks', contentDiscoveredItems(), 120))->toBeTrue()
            ->and($cache->put('blocks', contentDiscoveredItems()))->toBeTrue();
    });

    it('uses the default ttl it is configured with', function (): void {
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('get')->with('x.__index__', [])->andReturn([]);
        $store->shouldReceive('forever')->with('x.__index__', Mockery::type('array'))->andReturn(true);
        $store->shouldReceive('put')->once()->with('x.hooks', Mockery::type('array'), 30)->andReturn(true);

        expect((new LaravelDiscoveryCache($store, 'x.', 30))->put('hooks', contentDiscoveredItems()))->toBeTrue();
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
