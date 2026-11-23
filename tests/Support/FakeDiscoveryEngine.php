<?php

declare(strict_types=1);

namespace Tests\Support;

use ArrayObject;
use Closure;
use Illuminate\Support\Collection;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Exceptions\DiscoveryNotFoundException;
use Pollora\Discovery\Domain\Models\DiscoveryItems;
use Throwable;

/**
 * A hand-written discovery engine for tests that need a real DiscoveryManager.
 *
 * The manager is final in the pollora/discovery package, so a test builds one
 * around this engine, which implements the interface and nothing more: no
 * cache driver, no performance statistics (see InstrumentedFakeDiscoveryEngine).
 *
 * It records the name of every call, in order, in the shared list `$calls`
 * (an object, so a clone of the engine, as the manager makes for one location,
 * writes into the same list), and can be told to throw on a method.
 */
class FakeDiscoveryEngine implements DiscoveryEngineInterface
{
    /**
     * The name of every call received, in order.
     *
     * @var ArrayObject<int, string>
     */
    public ArrayObject $calls;

    /**
     * The locations the engine scans.
     *
     * @var list<DiscoveryLocationInterface>
     */
    public array $locations = [];

    /**
     * What scanning a location finds, when the test wants it to depend on the
     * location: it receives the location and returns the items found, keyed by
     * discovery identifier. The findings of discover() replace the items of
     * `$discovered`, in a list shared with the clones of the engine, as the
     * discoveries of the real engine are.
     *
     * @var (Closure(DiscoveryLocationInterface): array<string, list<mixed>>)|null
     */
    public ?Closure $scan = null;

    /**
     * The items found by the last discover(), keyed by identifier.
     *
     * @var ArrayObject<string, list<mixed>>
     */
    public ArrayObject $found;

    /**
     * The exception to throw when a method is called, keyed by method name.
     *
     * @var array<string, Throwable>
     */
    public array $failures = [];

    /**
     * @param  array<string, list<mixed>>  $discovered  The items each discovery holds, keyed by identifier
     * @param  bool  $knowsEveryIdentifier  Whether an identifier not in $discovered answers an empty discovery instead of throwing
     */
    public function __construct(public array $discovered = [], public bool $knowsEveryIdentifier = false)
    {
        $this->calls = new ArrayObject;
        $this->found = new ArrayObject;
    }

    /**
     * Make a scan of a location find what the closure returns for it.
     *
     * @param  Closure(DiscoveryLocationInterface): array<string, list<mixed>>  $scan
     */
    public function scanning(Closure $scan): static
    {
        $this->scan = $scan;

        return $this;
    }

    /**
     * Make a method throw the given exception each time it is called.
     */
    public function failOn(string $method, Throwable $failure): static
    {
        $this->failures[$method] = $failure;

        return $this;
    }

    /**
     * Whether the method was called at least once.
     */
    public function received(string $method): bool
    {
        return in_array($method, $this->calls->getArrayCopy(), true);
    }

    public function addLocation(DiscoveryLocationInterface $location): static
    {
        $this->record(__FUNCTION__);
        $this->locations[] = $location;

        return $this;
    }

    public function addLocations(array $locations): static
    {
        $this->record(__FUNCTION__);

        foreach ($locations as $location) {
            $this->locations[] = $location;
        }

        return $this;
    }

    public function addDiscovery(string $identifier, string|DiscoveryInterface $discovery): static
    {
        $this->record(__FUNCTION__);

        return $this;
    }

    public function addDiscoveries(array $discoveries): static
    {
        $this->record(__FUNCTION__);

        return $this;
    }

    public function discover(): static
    {
        $this->record(__FUNCTION__);

        if ($this->scan instanceof Closure) {
            $found = [];

            foreach ($this->locations as $location) {
                foreach (($this->scan)($location) as $identifier => $items) {
                    $found[$identifier] = [...($found[$identifier] ?? []), ...$items];
                }
            }

            $this->found->exchangeArray($found);
        }

        return $this;
    }

    public function apply(): static
    {
        $this->record(__FUNCTION__);

        return $this;
    }

    public function run(): static
    {
        $this->record(__FUNCTION__);

        return $this;
    }

    public function getDiscovery(string $identifier): DiscoveryInterface
    {
        $this->record(__FUNCTION__);

        $items = $this->found[$identifier] ?? $this->discovered[$identifier] ?? null;

        if ($items === null && ! $this->knowsEveryIdentifier) {
            throw DiscoveryNotFoundException::withIdentifier($identifier);
        }

        return new FakeDiscovery($identifier, new DiscoveryItems(['fake' => $items ?? []]));
    }

    public function getDiscoveries(): Collection
    {
        $this->record(__FUNCTION__);

        $discoveries = [];

        foreach ([...array_keys($this->discovered), ...array_keys($this->found->getArrayCopy())] as $identifier) {
            $discoveries[$identifier] = $this->getDiscovery($identifier);
        }

        return new Collection($discoveries);
    }

    public function getLocations(): Collection
    {
        $this->record(__FUNCTION__);

        return new Collection($this->locations);
    }

    public function clearCache(): static
    {
        $this->record(__FUNCTION__);

        return $this;
    }

    public function clearLocations(): static
    {
        $this->record(__FUNCTION__);
        $this->locations = [];

        return $this;
    }

    public function runDiscovery(string $identifier, DiscoveryInterface $discovery): static
    {
        $this->record(__FUNCTION__);

        return $this;
    }

    /**
     * Note a call, then throw what the test asked this method to throw.
     */
    private function record(string $method): void
    {
        $this->calls[] = $method;

        if (isset($this->failures[$method])) {
            throw $this->failures[$method];
        }
    }
}
