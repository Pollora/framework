<?php

declare(strict_types=1);

namespace Tests\Support\RedisObjectCache;

use Pollora\ObjectCache\Domain\Contracts\Clock;

/**
 * A clock that only moves when a test says so, to test an expiry without waiting.
 */
final class FrozenClock implements Clock
{
    public function __construct(private int $now = 1_000_000) {}

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
