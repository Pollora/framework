<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use Illuminate\Support\Arr;

/**
 * Service for detecting and configuring local development environments.
 *
 * This service provides automatic detection and configuration for common
 * local development environments like DDEV and Laradock.
 */
class LocalEnvironmentDetector
{
    /**
     * Default configuration values.
     *
     * @var array<string, string>
     */
    private const DEFAULT_CONFIG = [
        'siteUrl' => 'http://localhost',
        'dbHost' => 'localhost',
        'dbPort' => '3306',
        'dbName' => '',
        'dbUser' => 'root',
        'dbPassword' => '',
    ];

    /**
     * Configuration for common development environments
     */
    private static array $environments = [
        'ddev' => [
            'detector' => 'isDdev',
            'config' => [
                'siteUrl' => ['DDEV_PRIMARY_URL', 'https://pollora.ddev.site'],
                'dbHost' => ['DB_HOST', 'db'],
                'dbPort' => ['DB_PORT', '3306'],
                'dbName' => ['PGDATABASE', 'db'],
                'dbUser' => ['PGUSER', 'db'],
                'dbPassword' => ['MYSQL_PWD', ''],
            ],
        ],
        'laradock' => [
            'detector' => 'isLaradock',
            'config' => [
                'siteUrl' => ['APP_URL', 'http://localhost'],
                'dbHost' => ['MYSQL_HOST', 'mysql'],
                'dbPort' => ['MYSQL_PORT', '3306'],
                'dbName' => ['MYSQL_DATABASE', 'default'],
                'dbUser' => ['MYSQL_USER', 'root'],
                'dbPassword' => ['MYSQL_PASSWORD', 'root'],
            ],
        ],
    ];

    /**
     * Get configuration for the detected environment.
     *
     * Detects the current development environment and returns appropriate
     * configuration values. Falls back to default configuration if no
     * environment is detected.
     *
     * @return array<string, string> Environment configuration with following keys:
     *                               siteUrl, dbHost, dbPort, dbName, dbUser, dbPassword
     */
    public static function getConfig(): array
    {
        foreach (self::$environments as $settings) {
            if (is_callable($settings['detector'])) {
                $isDetected = call_user_func($settings['detector']);
            } else {
                $isDetected = self::{$settings['detector']}();
            }

            if ($isDetected) {
                return self::buildConfig($settings['config']);
            }
        }

        return self::DEFAULT_CONFIG;
    }

    /**
     * Check if running in DDEV environment.
     *
     * @return bool True if DDEV environment is detected
     */
    public static function isDdev(): bool
    {
        return getenv('IS_DDEV_PROJECT') === 'true';
    }

    /**
     * Check if running in Laradock environment.
     *
     * @return bool True if Laradock environment is detected
     */
    private function isLaradock(): bool
    {
        return ! (in_array(getenv('LARADOCK_PHP_VERSION'), ['', '0'], true) || getenv('LARADOCK_PHP_VERSION') === [] || getenv('LARADOCK_PHP_VERSION') === false);
    }

    /**
     * Build configuration from environment variables with fallbacks
     */
    private static function buildConfig(array $config): array
    {
        return collect($config)
            ->map(function ($value, $key) {
                [$envVar, $default] = Arr::wrap($value);

                return getenv($envVar) ?: $default;
            })
            ->all();
    }

    /**
     * Add a custom environment configuration.
     *
     * Registers a new environment type with its detection logic and configuration.
     *
     * @param  string  $name  Environment identifier
     * @param  callable  $detector  Function that returns bool indicating if environment is active
     * @param  array<string, mixed>  $config  Environment-specific configuration values
     */
    public static function addEnvironment(
        string $name,
        callable $detector,
        array $config
    ): void {
        self::$environments[$name] = [
            'detector' => $detector,
            'config' => $config,
        ];
    }

    /**
     * Get the name of the current environment.
     *
     * Attempts to detect the current environment from registered environments.
     *
     * @return string|null Environment identifier if detected, null otherwise
     */
    public static function getCurrentEnvironment(): ?string
    {
        foreach (self::$environments as $env => $settings) {
            if (is_callable($settings['detector'])) {
                $isDetected = call_user_func($settings['detector']);
            } else {
                $isDetected = self::{$settings['detector']}();
            }

            if ($isDetected) {
                return $env;
            }
        }

        return null;
    }
}
