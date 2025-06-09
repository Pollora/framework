<?php

declare(strict_types=1);

namespace Pollora\Proxy;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use wpdb;

/**
 * WordPress database proxy that integrates with Laravel's database connection.
 *
 * This class extends WordPress' native database class (wpdb) to redirect
 * database operations through Laravel's connection, ensuring a single
 * shared database connection between WordPress and Laravel.
 */
class WordPressDatabase extends wpdb
{
    /**
     * Laravel's database connection instance.
     */
    protected Connection $eloquentConnection;

    /**
     * Database connection handler.
     *
     * @var mixed PDO instance from Laravel's connection
     */
    public $dbh;

    /**
     * Query start time for performance monitoring.
     *
     * @var float
     */
    public $time_start;

    /**
     * Create a new WordPress database proxy instance.
     *
     * Initializes the Laravel database connection and configures
     * WordPress database settings accordingly.
     */
    public function __construct()
    {
        $this->eloquentConnection = DB::connection();

        [$dbUser, $dbPassword, $dbName, $dbHost] = $this->extractConfig();

        parent::__construct($dbUser, $dbPassword, $dbName, $dbHost);
    }

    /**
     * Verify if the current configuration is compatible with WordPress.
     *
     * Checks if all required database configuration parameters are present
     * and if the driver is MySQL (required by WordPress).
     *
     * @return bool True if configuration is valid for WordPress
     */
    public function hasValidConfiguration(): bool
    {
        $config = $this->eloquentConnection->getConfig();

        return isset($config['host'], $config['username'], $config['password'], $config['database']) && $config['driver'] === 'mysql';
    }

    /**
     * Extract database configuration from Eloquent connection.
     *
     * Retrieves and formats database connection parameters from Laravel's
     * configuration in a format compatible with WordPress.
     *
     * @return array{0: string, 1: string, 2: string, 3: string} Array of [username, password, database, host]
     */
    protected function extractConfig(): array
    {
        $config = $this->eloquentConnection->getConfig();

        $dbHost = ($config['host'] ?? 'localhost').(isset($config['port']) ? ':'.$config['port'] : '');
        $dbName = $config['database'] ?? '';
        $dbUser = $config['username'] ?? '';
        $dbPassword = $config['password'] ?? '';

        return [$dbUser, $dbPassword, $dbName, $dbHost];
    }

    /**
     * Connect to MySQL using mysqli.
     *
     * Overrides WordPress' mysqli connection to use Laravel's PDO connection instead.
     *
     * @param  string  $host  Database host
     * @param  string|null  $port  Database port
     * @param  string|null  $socket  Database socket
     * @param  int|null  $client_flags  Client connection flags
     */
    public function mysqli_real_connect(
        string $host,
        ?string $port = null,
        ?string $socket = null,
        ?int $client_flags = 0
    ): void {
        $this->dbh = $this->eloquentConnection->getPdo();
    }

    /**
     * Prevent usage of deprecated mysql_connect.
     *
     * Blocks attempts to use the old mysql_connect method which is no longer supported.
     *
     * @param  bool  $new_link  Whether to force a new connection
     * @param  int  $client_flags  Client connection flags
     *
     * @throws RuntimeException Always throws to prevent usage
     */
    public function mysql_connect(bool $new_link = false, int $client_flags = 0): never
    {
        throw new RuntimeException(
            'Using mysql_connect is deprecated and not supported. Please use mysqli_real_connect.'
        );
    }
}
