<?php

declare(strict_types=1);

namespace Pollora\Option\Domain\Contracts;

use Pollora\Option\Domain\Models\Option;

/**
 * Contract for WordPress option data access.
 */
interface OptionRepositoryInterface
{
    /**
     * Retrieve an option by key.
     *
     * @param  string  $key  The option key
     * @return Option|null The option instance or null if not found
     */
    public function get(string $key): ?Option;

    /**
     * Store a new option.
     *
     * @param  Option  $option  The option to store
     * @return bool True on success, false on failure
     */
    public function store(Option $option): bool;

    /**
     * Update an existing option.
     *
     * @param  Option  $option  The option to update
     * @return bool True on success, false on failure
     */
    public function update(Option $option): bool;

    /**
     * Delete an option by key.
     *
     * @param  string  $key  The option key to delete
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Check if an option exists.
     *
     * @param  string  $key  The option key to check
     * @return bool True if the option exists, false otherwise
     */
    public function exists(string $key): bool;
}
