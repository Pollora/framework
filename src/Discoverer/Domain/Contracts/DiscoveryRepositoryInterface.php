<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Contracts;

use Pollora\Discoverer\Domain\Models\DiscoveredClass;

/**
 * Interface for persistent discovery repository.
 *
 * Defines the contract for repositories that store discovered classes
 * for later retrieval, even across application restarts.
 * Unlike the registry which is in-memory only, repositories
 * are meant for longer-term storage.
 */
interface DiscoveryRepositoryInterface
{
    /**
     * Store a discovered class in the repository.
     */
    public function store(DiscoveredClass $discoveredClass): void;

    /**
     * Find all stored classes of a specific type.
     *
     * @return array<DiscoveredClass>
     */
    public function findByType(string $type): array;

    /**
     * Check if a class exists in the repository.
     */
    public function exists(string $className): bool;

    /**
     * Get all stored classes.
     *
     * @return array<string, array<DiscoveredClass>>
     */
    public function all(): array;

    /**
     * Persist the current state of the repository.
     *
     * This allows repositories to implement caching or storage strategies.
     */
    public function persist(): void;

    /**
     * Clear the repository.
     *
     * Removes all stored classes from the repository.
     */
    public function clear(): void;
}
