<?php

declare(strict_types=1);

namespace Tests\Support\RedisObjectCache;

use Pollora\ObjectCache\Application\ObjectCache;
use Pollora\ObjectCache\Domain\Contracts\CacheStore;
use Pollora\ObjectCache\Domain\GroupRegistry;
use Pollora\ObjectCache\Domain\KeyBuilder;
use Pollora\ObjectCache\Domain\Prefetch\PrefetchPlan;
use Pollora\ObjectCache\Domain\TtlPolicy;
use Pollora\ObjectCache\Infrastructure\Stores\InMemoryCacheStore;

/**
 * Builds an object cache for a test: a persistent store of the test's choice and
 * a fresh request cache.
 */
final class Caches
{
    public static function make(
        CacheStore $persistent,
        bool $multisite = false,
        ?InMemoryCacheStore $runtime = null,
        ?GroupRegistry $groups = null,
        ?TtlPolicy $ttl = null,
        ?PrefetchPlan $prefetch = null,
    ): ObjectCache {
        return new ObjectCache(
            $persistent,
            $runtime ?? new InMemoryCacheStore,
            new KeyBuilder('site', $multisite),
            $groups ?? new GroupRegistry,
            $ttl ?? new TtlPolicy,
            1,
            $prefetch,
        );
    }
}
