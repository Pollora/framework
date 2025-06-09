<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Services;

use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
use Pollora\Discoverer\Domain\Contracts\ScoutInterface;
use Pollora\Discoverer\Domain\Models\DiscoveredClass;

/**
 * Core domain service for class discovery.
 */
final class DiscoveryService
{
    /**
     * @param  array<ScoutInterface>  $scouts
     */
    public function __construct(
        private readonly DiscoveryRegistryInterface $registry,
        private readonly array $scouts
    ) {}

    /**
     * Discover and register classes using all registered scouts.
     */
    public function discoverAndRegister(): void
    {
        foreach ($this->scouts as $scout) {
            $discoveredClasses = $scout->discover();

            foreach ($discoveredClasses as $className) {
                $this->registry->register(
                    new DiscoveredClass($className, $scout->getType())
                );
            }
        }
    }

    /**
     * Get all discovered classes of a specific type.
     *
     * @return array<DiscoveredClass>
     */
    public function getByType(string $type): array
    {
        return $this->registry->getByType($type);
    }

    /**
     * Check if a class has been discovered.
     */
    public function hasDiscovered(string $className): bool
    {
        return $this->registry->has($className);
    }

    /**
     * Get all discovered classes.
     *
     * @return array<string, array<DiscoveredClass>>
     */
    public function getAllDiscovered(): array
    {
        return $this->registry->all();
    }
}
