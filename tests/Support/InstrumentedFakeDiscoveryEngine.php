<?php

declare(strict_types=1);

namespace Tests\Support;

use Throwable;

/**
 * A fake discovery engine with the two optional capabilities of the real
 * engine, which the manager and the tools around it look for with
 * `method_exists()`: its cache driver and its performance statistics.
 *
 * A Throwable given as driver or as statistics is thrown when it is read.
 */
class InstrumentedFakeDiscoveryEngine extends FakeDiscoveryEngine
{
    /**
     * @param  object|null  $driver  The cache driver, or the exception reading it throws
     * @param  array<string, mixed>|Throwable  $stats  The performance statistics, or the exception reading them throws
     * @param  array<string, list<mixed>>  $discovered  The items each discovery holds, keyed by identifier
     * @param  bool  $knowsEveryIdentifier  Whether an unknown identifier answers an empty discovery instead of throwing
     */
    public function __construct(
        public ?object $driver = null,
        public mixed $stats = [],
        array $discovered = [],
        bool $knowsEveryIdentifier = false,
    ) {
        parent::__construct($discovered, $knowsEveryIdentifier);
    }

    /**
     * The cache driver of the engine, as the real engine exposes it.
     */
    public function getCacheDriver(): ?object
    {
        if ($this->driver instanceof Throwable) {
            throw $this->driver;
        }

        return $this->driver;
    }

    /**
     * The performance statistics of the engine, as the real engine exposes them.
     *
     * @return array<string, mixed>
     */
    public function getPerformanceStats(): array
    {
        if ($this->stats instanceof Throwable) {
            throw $this->stats;
        }

        return $this->stats;
    }
}
