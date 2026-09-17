<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Pollora\Proxy\WordPressDatabase;

/*
|--------------------------------------------------------------------------
| Minimal wpdb stub for unit testing
|--------------------------------------------------------------------------
*/
if (! class_exists('wpdb')) {
    class wpdb
    {
        public $dbh;

        public string $prefix = 'wp_';

        public function __construct(public string $dbuser, public string $dbpassword, public string $dbname, public string $dbhost) {}
    }
}

describe('WordPressDatabase', function (): void {
    beforeEach(function (): void {
        $this->connection = Mockery::mock(Connection::class);
        $this->connection->shouldReceive('getConfig')
            ->andReturn([
                'driver' => 'mysql',
                'host' => 'db',
                'port' => 3306,
                'database' => 'wordpress',
                'username' => 'wp_user',
                'password' => 'secret',
            ])
            ->byDefault();

        // Bind a mock DatabaseManager that returns our connection
        $dbManager = Mockery::mock(DatabaseManager::class);
        $dbManager->shouldReceive('connection')
            ->andReturn($this->connection)
            ->byDefault();

        $app = Container::getInstance();
        $app->instance('db', $dbManager);
        Facade::setFacadeApplication($app);
        DB::clearResolvedInstances();
    });

    it('extends wpdb', function (): void {
        $proxy = new WordPressDatabase;

        expect($proxy)->toBeInstanceOf(wpdb::class);
    });

    it('extracts host with port from Laravel config', function (): void {
        $proxy = new WordPressDatabase;

        expect($proxy->dbhost)->toBe('db:3306');
        expect($proxy->dbname)->toBe('wordpress');
        expect($proxy->dbuser)->toBe('wp_user');
        expect($proxy->dbpassword)->toBe('secret');
    });

    it('extracts host without port when not configured', function (): void {
        $this->connection->shouldReceive('getConfig')
            ->andReturn([
                'driver' => 'mysql',
                'host' => 'localhost',
                'database' => 'wp',
                'username' => 'root',
                'password' => '',
            ]);

        $proxy = new WordPressDatabase;

        expect($proxy->dbhost)->toBe('localhost');
    });

    it('defaults host to localhost when missing', function (): void {
        $this->connection->shouldReceive('getConfig')
            ->andReturn([
                'driver' => 'mysql',
                'database' => 'wp',
                'username' => 'root',
                'password' => '',
            ]);

        $proxy = new WordPressDatabase;

        expect($proxy->dbhost)->toBe('localhost');
    });

    it('has valid configuration with complete MySQL config', function (): void {
        $proxy = new WordPressDatabase;

        expect($proxy->hasValidConfiguration())->toBeTrue();
    });

    it('has invalid configuration when driver is not mysql', function (): void {
        $this->connection->shouldReceive('getConfig')
            ->andReturn([
                'driver' => 'sqlite',
                'host' => 'localhost',
                'database' => 'wp',
                'username' => 'root',
                'password' => '',
            ]);

        $proxy = new WordPressDatabase;

        expect($proxy->hasValidConfiguration())->toBeFalse();
    });

    it('has invalid configuration when host is missing', function (): void {
        $this->connection->shouldReceive('getConfig')
            ->andReturn([
                'driver' => 'mysql',
                'database' => 'wp',
                'username' => 'root',
                'password' => '',
            ]);

        $proxy = new WordPressDatabase;

        expect($proxy->hasValidConfiguration())->toBeFalse();
    });

    it('uses Laravel PDO via mysqli_real_connect', function (): void {
        $pdo = Mockery::mock(PDO::class);
        $this->connection->shouldReceive('getPdo')->once()->andReturn($pdo);

        $proxy = new WordPressDatabase;
        $proxy->mysqli_real_connect('db');

        expect($proxy->dbh)->toBe($pdo);
    });

    it('throws RuntimeException on mysql_connect', function (): void {
        $proxy = new WordPressDatabase;

        expect(fn () => $proxy->mysql_connect())
            ->toThrow(RuntimeException::class, 'Using mysql_connect is deprecated');
    });
});
