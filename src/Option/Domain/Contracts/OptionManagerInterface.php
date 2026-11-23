<?php

declare(strict_types=1);

namespace Pollora\Option\Domain\Contracts;

/**
 * Contract of the WordPress option manager fronted by the Option facade.
 */
interface OptionManagerInterface
{
    /**
     * Retrieve an option value, or the default when it does not exist.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Create or update an option (upsert).
     */
    public function set(string $key, mixed $value): bool;

    /**
     * Update an existing option.
     */
    public function update(string $key, mixed $value): bool;

    /**
     * Delete an option.
     */
    public function delete(string $key): bool;

    /**
     * Whether the option exists.
     */
    public function exists(string $key): bool;

    /**
     * Laravel-style alias of {@see delete()}.
     */
    public function forget(string $key): bool;
}
