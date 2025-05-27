<?php

declare(strict_types=1);

namespace Pollora\WordPress\Infrastructure\Services;

/**
 * Service for interacting with WordPress options.
 *
 * This service wraps WordPress options functions to provide a more
 * object-oriented interface and better type safety.
 *
 * @codeCoverageIgnore This class is a wrapper around WordPress functions
 */
class Options implements OptionsInterface
{
    /**
     * Get an option value.
     *
     * @template T
     * @param string $key The option key
     * @param T $default The default value if the option doesn't exist
     * @return T The option value
     *
     * @phpstan-ignore-next-line WordPress function
     */
    public function get(string $key, mixed $default = null): mixed
    {
        /** @phpstan-ignore-next-line WordPress function */
        return \get_option($key, $default);
    }

    /**
     * Update an option value.
     *
     * @param string $key The option key
     * @param mixed $value The new value
     *
     * @phpstan-ignore-next-line WordPress function
     */
    public function update(string $key, mixed $value): bool
    {
        /** @phpstan-ignore-next-line WordPress function */
        return \update_option($key, $value);
    }

    /**
     * Delete an option.
     *
     * @phpstan-ignore-next-line WordPress function
     */
    public function delete(string $key): bool
    {
        /** @phpstan-ignore-next-line WordPress function */
        return \delete_option($key);
    }

    /**
     * Check if an option exists.
     *
     * @phpstan-ignore-next-line WordPress function
     */
    public function exists(string $key): bool
    {
        /** @phpstan-ignore-next-line WordPress function */
        return \get_option($key) !== false;
    }
} 