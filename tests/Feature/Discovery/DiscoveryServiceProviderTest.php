<?php

declare(strict_types=1);

use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Infrastructure\Providers\DiscoveryServiceProvider;
use Pollora\Discovery\Infrastructure\Services\DiscoveryEngine;
use Pollora\Discovery\Infrastructure\Services\InstancePool;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Discovery\Infrastructure\Services\ServiceProviderDiscovery;

beforeEach(function (): void {
    // DiscoveryServiceProvider depends on DebugDetectorInterface
    $this->app->singleton(DebugDetectorInterface::class, fn () => Mockery::mock(DebugDetectorInterface::class, [
        'isDebugMode' => false,
    ]));

    $this->app->register(DiscoveryServiceProvider::class);
});

describe('DiscoveryServiceProvider', function (): void {
    it('binds ReflectionCacheInterface as singleton', function (): void {
        $cache = $this->app->make(ReflectionCacheInterface::class);

        expect($cache)->toBeInstanceOf(ReflectionCache::class);
        expect($this->app->make(ReflectionCacheInterface::class))->toBe($cache);
    });

    it('binds InstancePool as singleton', function (): void {
        $pool = $this->app->make(InstancePool::class);

        expect($pool)->toBeInstanceOf(InstancePool::class);
        expect($this->app->make(InstancePool::class))->toBe($pool);
    });

    it('binds DiscoveryEngineInterface to DiscoveryEngine', function (): void {
        $engine = $this->app->make(DiscoveryEngineInterface::class);

        expect($engine)->toBeInstanceOf(DiscoveryEngine::class);
    });

    it('returns same DiscoveryEngine instance (singleton)', function (): void {
        $engine1 = $this->app->make(DiscoveryEngineInterface::class);
        $engine2 = $this->app->make(DiscoveryEngineInterface::class);

        expect($engine1)->toBe($engine2);
    });

    it('binds DiscoveryManager as singleton', function (): void {
        $manager = $this->app->make(DiscoveryManager::class);

        expect($manager)->toBeInstanceOf(DiscoveryManager::class);
        expect($this->app->make(DiscoveryManager::class))->toBe($manager);
    });

    it('registers ServiceProviderDiscovery as singleton for auto-registration', function (): void {
        expect($this->app->bound(ServiceProviderDiscovery::class))->toBeTrue();
    });

    it('declares provided services', function (): void {
        $provider = $this->app->getProvider(DiscoveryServiceProvider::class);
        $provides = $provider->provides();

        expect($provides)->toContain(DiscoveryEngineInterface::class);
        expect($provides)->toContain(DiscoveryManager::class);
        expect($provides)->toContain(ReflectionCacheInterface::class);
        expect($provides)->toContain(InstancePool::class);
    });
});
