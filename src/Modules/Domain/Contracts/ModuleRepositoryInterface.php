<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

use Pollora\Collection\Domain\Contracts\CollectionInterface;

interface ModuleRepositoryInterface
{
    /**
     * Get all modules.
     */
    public function all(): array;

    /**
     * Get all modules as collection.
     */
    public function toCollection(): CollectionInterface;

    /**
     * Find a module by name.
     */
    public function find(string $name): ?ModuleInterface;

    /**
     * Find a module by name or fail.
     */
    public function findOrFail(string $name): ModuleInterface;

    /**
     * Check if module exists.
     */
    public function has(string $name): bool;

    /**
     * Get modules by status.
     */
    public function getByStatus(bool $status): array;

    /**
     * Get all enabled modules.
     */
    public function allEnabled(): array;

    /**
     * Get all disabled modules.
     */
    public function allDisabled(): array;

    /**
     * Get ordered modules.
     */
    public function getOrdered(string $direction = 'asc'): array;

    /**
     * Scan for modules.
     */
    public function scan(): array;

    /**
     * Register all modules.
     */
    public function register(): void;

    /**
     * Boot all modules.
     */
    public function boot(): void;

    /**
     * Count modules.
     */
    public function count(): int;
}
