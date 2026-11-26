<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use DB;
use Illuminate\Container\Container;
use Pollora\Services\WordPress\Database\DatabaseConnectionService;
use Pollora\Services\WordPress\Database\EnvironmentFileHandler;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;
use Pollora\WordPress\DatabaseSettings;

use function Laravel\Prompts\info;

/**
 * Service for handling WordPress database configuration and connection.
 *
 * This service manages database configuration, connection testing, and environment
 * file updates for WordPress installations.
 */
class DatabaseService
{
    /**
     * The SQLite file used when the project does not configure one, relative to the project root.
     */
    public const string SQLITE_FILE = 'database/database.sqlite';

    /**
     * The mode of a SQLite file the installer creates: the owner and the group, nobody else.
     */
    public const int SQLITE_FILE_MODE = 0640;

    /**
     * Create a new database service instance.
     *
     * @param  DatabaseConnectionService  $connectionService  Service for testing connections
     * @param  EnvironmentFileHandler  $envHandler  Handler for .env file updates
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
     * @param  DatabaseConfig  $config  The database configuration
     */
    public function configure(DatabaseConfig $config): void
    {
        // Ensure we have a working connection
        $config = $this->connectionService->ensureConnection($config);

        // Update env file only after successful connection
        $this->updateConfiguration($config);

        info('Environment file has been saved.');
    }

    /**
     * Point the project at a SQLite database file, for development, CI or a demo.
     *
     * Creates the empty file when it does not exist and writes the connection to
     * the environment file. The file configured for the `sqlite` connection is
     * kept when it looks like one (`.sqlite`, `.sqlite3`, `.db`); otherwise
     * `DB_DATABASE`, which a MySQL setup fills with a database name, would be read as a
     * path, and the default `database/database.sqlite` is used. WordPress still
     * needs the SQLite Database Integration plugin: this does not install it.
     * A file the method creates is mode 0640; one that already exists keeps its mode.
     *
     * @return string The path written to `DB_DATABASE`
     *
     * @throws DatabaseConnectionException When the file cannot be created
     */
    public function configureSqlite(): string
    {
        $configured = config('database.connections.sqlite.database');

        $path = is_string($configured) && preg_match('/\.(sqlite3?|db)$/i', $configured) === 1
            ? $configured
            : self::SQLITE_FILE;

        // $path is never empty nor in-memory here, so there is always a file
        $file = (string) DatabaseSettings::sqlitePath(['database' => $path]);

        if (! is_file($file)) {
            if (! is_dir(dirname($file)) && ! @mkdir(dirname($file), 0755, true) && ! is_dir(dirname($file))) {
                throw new DatabaseConnectionException(sprintf('Cannot create the directory of the SQLite database: %s', dirname($file)));
            }

            // Created unreadable by others from the start (0640, whatever the umask is): the file
            // holds the WordPress password hashes. Not 0600, because PHP-FPM and the CLI can be two
            // users of the same group.
            $previousUmask = umask(0137);

            try {
                $created = @file_put_contents($file, '') !== false;
            } finally {
                umask($previousUmask);
            }

            if (! $created) {
                throw new DatabaseConnectionException(sprintf('Cannot create the SQLite database file: %s', $file));
            }

            @chmod($file, self::SQLITE_FILE_MODE);
        }

        $this->envHandler->updateEnvFile([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $path,
        ], append: true);

        info('Environment file has been saved.');

        return $path;
    }

    private function updateConfiguration(DatabaseConfig $config): void
    {
        $this->envHandler->updateEnvFile([
            // A project that chose Laravel's MariaDB driver keeps it
            'DB_CONNECTION' => $this->chosenDriver(),
            'DB_HOST' => $config->host,
            'DB_PORT' => (string) $config->port,
            'DB_DATABASE' => $config->name,
            'DB_USERNAME' => $config->username,
            'DB_PASSWORD' => $config->password,
            'APP_URL' => $config->siteUrl,
        ]);
    }

    /**
     * The driver to write: MariaDB's when the project chose it, MySQL's otherwise.
     */
    private function chosenDriver(): string
    {
        $default = Container::getInstance()->bound('config') ? config('database.default') : null;

        return $default === DatabaseSettings::MARIADB ? DatabaseSettings::MARIADB : DatabaseSettings::MYSQL;
    }
}
