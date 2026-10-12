<?php

declare(strict_types=1);
use Illuminate\Support\Collection;
use Pollora\Attributes\SkipDiscovery;
use Pollora\Discovery\Domain\Contracts\ConfigurableDiscoveryInterface;
use Pollora\Discovery\Domain\Services\HasConfiguringSupport;
use Pollora\Discovery\Infrastructure\Services\DiscoveryCacheManager;
use Pollora\Discovery\Infrastructure\Services\DiscoveryEngine;
use Pollora\Discovery\Infrastructure\Services\InstancePool;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;

/**
 * The part of discovery that becomes the `pollora/discovery` package must run
 * without Laravel's application: no container, no config, no facades, no
 * framework module. Laravel stays in the adapters, the provider, the console
 * commands, DiscoveryRegistrar and ServiceProviderDiscovery.
 *
 * HasConfiguringSupport and ConfigurableDiscoveryInterface stay in the
 * framework too: they build post types and taxonomies through pollora/entity,
 * which a discovery package has no reason to require.
 */
arch('the package part of discovery stays free of the Laravel application')
    ->expect([
        'Pollora\Discovery\Domain',
        'Pollora\Discovery\Application',
        'Pollora\Discovery\Infrastructure\Cache',
        'Pollora\Discovery\Infrastructure\Resolvers',
        DiscoveryCacheManager::class,
        DiscoveryEngine::class,
        InstancePool::class,
        ReflectionCache::class,
    ])
    ->toOnlyUse([
        'Pollora\Discovery',
        SkipDiscovery::class,
        'Spatie\StructureDiscoverer',
        'Psr\Log',
        'Psr\SimpleCache',
        'Psr\Container',
        Collection::class,
        // HasInstancePool falls back to Laravel's resolve() only when it is defined.
        'resolve',
    ])
    ->ignoring([
        HasConfiguringSupport::class,
        ConfigurableDiscoveryInterface::class,
    ]);
