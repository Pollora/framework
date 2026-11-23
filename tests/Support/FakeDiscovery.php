<?php

declare(strict_types=1);

namespace Tests\Support;

use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryItemsInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * A discovery that holds the items it was given and discovers nothing.
 */
final class FakeDiscovery implements DiscoveryInterface
{
    public function __construct(private readonly string $identifier, private DiscoveryItemsInterface $items) {}

    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure, ?ReflectionCacheInterface $reflectionCache = null): void {}

    public function getItems(): DiscoveryItemsInterface
    {
        return $this->items;
    }

    public function setItems(DiscoveryItemsInterface $items): void
    {
        $this->items = $items;
    }

    public function apply(): void {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
