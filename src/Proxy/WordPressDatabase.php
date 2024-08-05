<?php

namespace Pollen\Proxy;

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
    
    /** @var \PDO|false|null */
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

    protected function extractConfig(): array
    {
        $config = $this->eloquentConnection->getConfig();

        $dbHost = $config['host'].(isset($config['port']) ? ':'.$config['port'] : '');
        $dbName = $config['database'];
        $dbUser = $config['username'];
        $dbPassword = $config['password'];

        return [$dbUser, $dbPassword, $dbName, $dbHost];
    }

    public function mysqli_real_connect($host, $port, $socket, $client_flags)
    {
        $this->dbh = $this->eloquentConnection->getPdo();
        dd($this->dbh);
    }

    /**
     * @throws \Exception
     */
    public function mysql_connect($new_link, $client_flags)
    {
        throw new Exception('Using mysql_connect is deprecated and not supported. Please use mysqli_real_connect.');
    }
}
