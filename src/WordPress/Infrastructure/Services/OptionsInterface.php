<?php

declare(strict_types=1);

namespace Pollora\WordPress\Infrastructure\Services;

/**
 * Interface for interacting with WordPress options.
 */
interface OptionsInterface
{
    /**
     * Get an option value.
     *
     * @template T
     *
     * @param  string  $key  The option key
     * @param  T  $default  The default value if the option doesn't exist
     * @return T The option value
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Update an option value.
     *
     * @param  string  $key  The option key
     * @param  mixed  $value  The new value
     */
    public function update(string $key, mixed $value): bool;

    /**
     * Delete an option.
     */
    public function delete(string $key): bool;

    /**
     * Check if an option exists.
     */
    public function exists(string $key): bool;
}
