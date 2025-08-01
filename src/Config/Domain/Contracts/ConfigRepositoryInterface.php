<?php

declare(strict_types=1);

namespace Pollora\Config\Domain\Contracts;

/**
 * Interface for configuration repository abstraction.
 *
 * Provides access to configuration values without framework dependency.
 */
interface ConfigRepositoryInterface
{
    /**
     * Get a configuration value by key.
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null);

    /**
     * Set a configuration value by key.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool;
}
