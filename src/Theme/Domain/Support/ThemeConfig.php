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
     * @param string $key The configuration key to retrieve
     * @param mixed $default The default value if the key is not found
     * @return mixed The configuration value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$configRepository === null) {
            self::$configRepository = app(ConfigRepositoryInterface::class);
        }

        return self::$configRepository->get($key, $default);
    }
} 