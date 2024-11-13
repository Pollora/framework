<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use DB;
use Illuminate\Support\Facades\File;
use Pollora\Services\WordPress\Database\DatabaseConnectionService;
use Pollora\Services\WordPress\Database\EnvironmentFileHandler;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

class DatabaseService
{
    public function __construct(
        private readonly DatabaseConnectionService $connectionService,
        private readonly EnvironmentFileHandler $envHandler
    ) {}

    public function isConfigured(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

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
