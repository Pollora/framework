<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Domain\Contracts;

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
}
