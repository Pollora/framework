<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Store;
use Pollora\ObjectCache\Application\ObjectCache;
use Pollora\ObjectCache\Domain\CacheValue;

/**
 * Laravel's cache store backed by the WordPress object cache.
 *
 * `Cache::store('wordpress')` reads and writes the same keys as `wp_cache_*`
 * does in one group (`laravel` by default), under the same site prefix, so a
 * value put from Laravel can be read by WordPress code that asks for that group.
 *
 * Reads bypass the request cache: Laravel code (counters, locks, flags) expects
 * to see what another process wrote. When Redis is unreachable the store
 * degrades like the object cache does: reads miss and writes report failure,
 * nothing throws.
 */
final readonly class ObjectCacheStore implements Store
{
    /**
     * @param  string  $group  The object cache group holding Laravel's entries.
     */
    public function __construct(private ObjectCache $cache, private string $group = 'laravel') {}

    /**
     * Read one entry.
     *
     * @param  string  $key
     */
    public function get($key): mixed
    {
        return $this->cache->get($key, $this->group, true)?->value;
    }

    /**
     * Read several entries; a missing one comes back as null.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $values = [];

        foreach ($this->cache->getMany($keys, $this->group, true) as $key => $value) {
            $values[$key] = $value?->value;
        }

        return $values;
    }

    /**
     * Store an entry for a number of seconds; a lapse of zero or less removes it.
     *
     * @param  string  $key
     * @param  int  $seconds
     */
    public function put($key, $value, $seconds): bool
    {
        if ($seconds <= 0) {
            $this->forget($key);

            return true;
        }

        return $this->cache->set($key, $value, $this->group, $seconds);
    }

    /**
     * Store an entry only if none exists, in one atomic step.
     *
     * Laravel's repository calls this for `Cache::add()` when the store has it,
     * which is what makes a lock built on a key safe across processes.
     *
     * @param  string  $key
     * @param  int  $seconds
     */
    public function add($key, $value, $seconds): bool
    {
        if ($seconds <= 0) {
            return false;
        }

        return $this->cache->add($key, $value, $this->group, $seconds);
    }

    /**
     * Store several entries for the same number of seconds.
     *
     * @param  array<string, mixed>  $values
     * @param  int  $seconds
     */
    public function putMany(array $values, $seconds): bool
    {
        $stored = true;

        foreach ($values as $key => $value) {
            $stored = $this->put($key, $value, $seconds) && $stored;
        }

        return $stored;
    }

    /**
     * Add to the entry's number; false when it does not exist or is not a number.
     *
     * @param  string  $key
     * @param  int  $value
     */
    public function increment($key, $value = 1): int|float|false
    {
        return $this->cache->increment($key, $value, $this->group);
    }

    /**
     * Subtract from the entry's number; false when it does not exist or is not a number.
     *
     * @param  string  $key
     * @param  int  $value
     */
    public function decrement($key, $value = 1): int|float|false
    {
        return $this->cache->decrement($key, $value, $this->group);
    }

    /**
     * Store an entry without a lapse (the configured `max_ttl` still applies).
     *
     * @param  string  $key
     */
    public function forever($key, $value): bool
    {
        return $this->cache->set($key, $value, $this->group, 0);
    }

    /**
     * Give an existing entry a new lapse, counted from now.
     *
     * Read then write, not one atomic command: a writer landing in between wins
     * the race and its value is overwritten with the one read here.
     *
     * @param  string  $key
     * @param  int  $seconds
     */
    public function touch($key, $seconds): bool
    {
        $current = $this->cache->get($key, $this->group, true);

        if (! $current instanceof CacheValue) {
            return false;
        }

        return $this->cache->set($key, $current->value, $this->group, max(0, $seconds));
    }

    /**
     * Remove one entry.
     *
     * @param  string  $key
     */
    public function forget($key): bool
    {
        return $this->cache->delete($key, $this->group);
    }

    /**
     * Empty the entries of this store's group, on every blog of this site.
     *
     * Not the whole object cache: WordPress's own groups stay.
     */
    public function flush(): bool
    {
        return $this->cache->flushGroup($this->group);
    }

    /**
     * The key prefix: none, the object cache prefixes every key with the site itself.
     */
    public function getPrefix(): string
    {
        return '';
    }
}
