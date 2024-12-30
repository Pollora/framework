<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use DB;
use Pollora\Services\WordPress\Database\DatabaseConnectionService;
use Pollora\Services\WordPress\Database\EnvironmentFileHandler;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

/**
 * Service for handling WordPress database configuration and connection.
 *
 * This service manages database configuration, connection testing, and environment
 * file updates for WordPress installations.
 */
class DatabaseService
{
    /**
     * Create a new database service instance.
     *
     * @param DatabaseConnectionService $connectionService Service for testing connections
     * @param EnvironmentFileHandler $envHandler Handler for .env file updates
     */
    public function __construct(
        private readonly DatabaseConnectionService $connectionService,
        private readonly EnvironmentFileHandler $envHandler
    ) {}

    /**
     * Check if the database is properly configured.
     *
     * @return bool True if database connection can be established
     */
    public function isConfigured(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Configure the database connection.
     *
     * Tests the connection and updates the environment file with the
     * provided configuration.
     *
     * @param DatabaseConfig $config The database configuration
     */
    public function configure(DatabaseConfig $config): void
    {
        // Ensure we have a working connection
        $config = $this->connectionService->ensureConnection($config);

        // Update env file only after successful connection
        $this->updateConfiguration($config);

        info('Environment file has been saved.');
    }

    private function updateConfiguration(DatabaseConfig $config): void
    {
        $this->envHandler->updateEnvFile([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $config->host,
            'DB_PORT' => (string) $config->port,
            'DB_DATABASE' => $config->name,
            'DB_USERNAME' => $config->username,
            'DB_PASSWORD' => $config->password,
            'APP_URL' => $config->siteUrl,
        ]);
    }
}
