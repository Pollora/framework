<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Support;

use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;

/**
 * Theme configuration access utility class.
 *
 * Provides a clean, object-oriented interface for accessing theme configuration
 * values while maintaining hexagonal architecture principles.
 */
class ThemeConfig
{
    private static ?ConfigRepositoryInterface $configRepository = null;

    /**
     * Set the configuration repository.
     */
    public static function setRepository(ConfigRepositoryInterface $repository): void
    {
        self::$configRepository = $repository;
    }

    /**
     * Get a configuration value from the theme config.
     *
     * @param  string  $key  The configuration key to retrieve
     * @param  mixed  $default  The default value if the key is not found
     * @return mixed The configuration value
     *
     * @throws \RuntimeException If the config repository is not set and no default is provided
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $key = 'theme.'.$key;

        if (! self::$configRepository instanceof \Pollora\Config\Domain\Contracts\ConfigRepositoryInterface) {
            // If no default is provided, throw the exception
            if (func_num_args() === 1) {
                throw new \RuntimeException(
                    'ConfigRepository not initialized. Call ThemeConfig::setRepository() before using this class.'
                );
            }

            // Return the default value if repository is not initialized
            return $default;
        }

        return self::$configRepository->get($key, $default);
    }

    /**
     * Check if the configuration repository is initialized.
     */
    public static function isInitialized(): bool
    {
        return self::$configRepository instanceof \Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
    }

    /**
     * Reset the configuration repository (mainly for testing).
     */
    public static function reset(): void
    {
        self::$configRepository = null;
    }
}
