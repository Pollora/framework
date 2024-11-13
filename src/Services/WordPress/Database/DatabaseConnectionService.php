<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Database;

use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class DatabaseConnectionService
{
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
                    throw new DatabaseConnectionException(
                        'Could not establish database connection. Please check your configuration.'
                    );
                }

                // Get new configuration
                $config = DatabaseConfig::fromPrompts();
            }
        }
    }

    private function testConnection(DatabaseConfig $config): void
    {
        spin(
            message: 'Testing database connection...',
            callback: fn () => new \PDO(
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

    private function shouldRetry(): bool
    {
        return confirm(
            label: 'Would you like to try different database credentials?',
            default: true,
        );
    }
}
