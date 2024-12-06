<?php

declare(strict_types=1);

namespace Pollora\Proxy;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use wpdb;

/**
 * Replace WordPress' database calls to Laravel's database connection to hold a single database connection
 */
class WordPressDatabase extends wpdb
{
    protected Connection $eloquentConnection;

    public $dbh;

    /**
     * @var float
     */
    public $time_start;

    public function __construct()
    {
        $this->eloquentConnection = DB::connection();

        [$dbUser, $dbPassword, $dbName, $dbHost] = $this->extractConfig();

        parent::__construct($dbUser, $dbPassword, $dbName, $dbHost);
    }

    /**
     * Verify if the current configuration is compatible with WordPress
     */
    public function hasValidConfiguration(): bool
    {
        $config = $this->eloquentConnection->getConfig();

        return $config['driver'] === 'mysql'
            && isset($config['host'])
            && isset($config['username'])
            && isset($config['password'])
            && isset($config['database']);
    }

    /**
     * Extract database configuration from Eloquent connection
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
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
     * Connect to MySQL using mysqli
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
     * Prevent usage of deprecated mysql_connect
     *
     * @throws Exception
     */
    public function mysql_connect($new_link = false, $client_flags = 0): never
    {
        throw new Exception(
            'Using mysql_connect is deprecated and not supported. Please use mysqli_real_connect.'
        );
    }
}
