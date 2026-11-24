<?php

declare(strict_types=1);

namespace Tests\Support\RedisObjectCache;

use Pollora\ObjectCache\Domain\CacheValue;
use Pollora\ObjectCache\Domain\Contracts\CacheStore;
use Pollora\ObjectCache\Domain\Exceptions\StoreUnavailableException;

/**
 * A store whose backend is down: every call fails, and the calls are counted.
 */
final class UnavailableStore implements CacheStore
{
    public int $calls = 0;

    public function get(string $key): ?CacheValue
    {
        return $this->fail();
    }

    public function getMany(array $keys): array
    {
        return $this->fail();
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        return $this->fail();
    }

    public function add(string $key, mixed $value, int $ttl): bool
    {
        return $this->fail();
    }

    public function replace(string $key, mixed $value, int $ttl): bool
    {
        return $this->fail();
    }

    public function delete(string $key): bool
    {
        return $this->fail();
    }

    public function adjust(string $key, int $delta): int|float|false
    {
        return $this->fail();
    }

    public function deleteByPrefix(string $prefix): int
    {
        return $this->fail();
    }

    private function fail(): never
    {
        $this->calls++;

        throw new StoreUnavailableException('Redis is down.');
    }
}
