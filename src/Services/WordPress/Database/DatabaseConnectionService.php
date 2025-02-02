<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Database;

use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

/**
 * Service for managing database connections in WordPress.
 *
 * This service handles database connection testing and configuration,
 * with support for interactive credential prompts and error handling.
 */
class DatabaseConnectionService
{
    /**
     * Ensure a valid database connection can be established.
     *
     * Tests the database connection and prompts for new credentials if needed.
     *
     * @param  DatabaseConfig  $config  The database configuration to test
     * @return DatabaseConfig The validated configuration (may be updated if retried)
     *
     * @throws DatabaseConnectionException When connection fails and user aborts retry
     */
    public function ensureConnection(DatabaseConfig $config): DatabaseConfig
    {
        while (true) {
            try {
                $this->testConnection($config);
                info('Database connection successful!');

                return $config;
            } catch (\Exception $e) {
                error("Database connection failed: {$e->getMessage()}");

                if (! $this->shouldRetry()) {
                    throw new DatabaseConnectionException('Could not establish database connection. Please check your configuration.', $e->getCode(), $e);
                }

                // Get new configuration
                $config = DatabaseConfig::fromPrompts();
            }
        }
    }

    /**
     * Test the database connection with given configuration.
     *
     * @param  DatabaseConfig  $config  The database configuration to test
     *
     * @throws \PDOException When connection fails
     */
    private function testConnection(DatabaseConfig $config): void
    {
        spin(
            message: 'Testing database connection...',
            callback: fn (): \PDO => new \PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s',
                    $config->host,
                    $config->port,
                    $config->name
                ),
                $config->username,
                $config->password,
                [
                    \PDO::ATTR_TIMEOUT => 5, // 5 seconds timeout
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]
            )
        );
    }

    /**
     * Check if user wants to retry with different credentials.
     *
     * @return bool True if should retry, false otherwise
     */
    private function shouldRetry(): bool
    {
        return confirm(
            label: 'Would you like to try different database credentials?',
            default: true,
        );
    }
}
