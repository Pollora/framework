<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Contracts;

/**
 * Interface for discovery registry.
 *
 * Defines the contract for registry objects that store and manage
 * discovered classes by type.
 */
interface DiscoveryRegistry
{
    /**
     * Register a class with the registry.
     *
     * @param string $class Fully qualified class name
     * @param string $type Type identifier for the class
     * @return void
     */
    public function register(string $class, string $type): void;

    /**
     * Get all registered classes of a specific type.
     *
     * @param string $type Type identifier
     * @return array<string> Array of class names
     */
    public function getByType(string $type): array;

    /**
     * Check if a class is registered.
     *
     * @param string $class Fully qualified class name
     * @return bool True if the class is registered
     */
    public function has(string $class): bool;

    /**
     * Get all registered classes.
     *
     * @return array<string, array<string>> Array of classes by type
     */
    public function all(): array;
}
