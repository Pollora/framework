<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Contracts;

use Pollora\Discoverer\Domain\Models\DiscoveredClass;

/**
 * Interface for discovery registry.
 *
 * Defines the contract for registry objects that store and manage
 * discovered classes by type.
 */
interface DiscoveryRegistryInterface
{
    /**
     * Register a discovered class with the registry.
     */
    public function register(DiscoveredClass $discoveredClass): void;

    /**
     * Get all registered classes of a specific type.
     *
     * @return array<DiscoveredClass>
     */
    public function getByType(string $type): array;

    /**
     * Check if a class is registered.
     */
    public function has(string $className): bool;

    /**
     * Get all registered classes.
     *
     * @return array<string, array<DiscoveredClass>>
     */
    public function all(): array;
}
