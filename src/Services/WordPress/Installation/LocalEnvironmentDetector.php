<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use Illuminate\Support\Arr;

class LocalEnvironmentDetector
{
    /**
     * Default configuration
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
     * Get environment configuration based on detection
     */
    public static function getConfig(): array
    {
        foreach (self::$environments as $env => $settings) {
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
     * Check if running in DDEV environment
     */
    private static function isDdev(): bool
    {
        return getenv('IS_DDEV_PROJECT') === 'true';
    }

    /**
     * Check if running in Laradock environment
     */
    private static function isLaradock(): bool
    {
        return ! empty(getenv('LARADOCK_PHP_VERSION'));
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
     * Add a custom environment configuration
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
     * Get current environment name
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
