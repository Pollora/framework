<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Services;

use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
use Pollora\Discoverer\Domain\Contracts\DiscoveryRepositoryInterface;
use Pollora\Discoverer\Domain\Models\DiscoveredClass;

/**
 * Service for managing discovered classes through a persistent repository.
 *
 * This service provides an abstraction for working with the discovery repository,
 * including synchronization between runtime registry and persistent storage.
 */
final class DiscoveryRepositoryService
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly DiscoveryRepositoryInterface $repository,
        private readonly ?DiscoveryRegistryInterface $registry = null
    ) {}

    /**
     * Store a discovered class.
     *
     * @param  string  $className  The fully qualified class name
     * @param  string  $type  The type identifier
     */
    public function storeClass(string $className, string $type): void
    {
        $discoveredClass = new DiscoveredClass($className, $type);
        $this->repository->store($discoveredClass);
    }

    /**
     * Retrieve all classes of a specific type.
     *
     * @param  string  $type  The type identifier
     * @return array<DiscoveredClass> Array of discovered classes
     */
    public function getByType(string $type): array
    {
        return $this->repository->findByType($type);
    }

    /**
     * Check if a class exists in the repository.
     *
     * @param  string  $className  The fully qualified class name
     * @return bool True if the class exists
     */
    public function exists(string $className): bool
    {
        return $this->repository->exists($className);
    }

    /**
     * Save current state to persistent storage.
     */
    public function persist(): void
    {
        $this->repository->persist();
    }

    /**
     * Sync all classes from the runtime registry to the persistent repository.
     *
     * This allows saving the current state of discovered classes to
     * the persistent repository.
     */
    public function syncFromRegistry(): void
    {
        if ($this->registry === null) {
            return;
        }

        $registryClasses = $this->registry->all();

        foreach ($registryClasses as $type => $classes) {
            foreach ($classes as $class) {
                $this->repository->store($class);
            }
        }

        $this->repository->persist();
    }

    /**
     * Get all stored classes.
     *
     * @return array<string, array<DiscoveredClass>> Array of discovered classes by type
     */
    public function getAll(): array
    {
        return $this->repository->all();
    }

    /**
     * Clear all stored classes.
     */
    public function clear(): void
    {
        $this->repository->clear();
    }
}
