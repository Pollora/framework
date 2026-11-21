<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Pollora\Services\WordPress\Database\DatabaseConnectionService;
use Pollora\Services\WordPress\Database\EnvironmentFileHandler;
use Pollora\Services\WordPress\Installation\DatabaseConnectionException;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Psr\Log\NullLogger;

beforeEach(function (): void {
    // configureSqlite() reports through the info() helper, which logs.
    Container::getInstance()->instance('log', new NullLogger);

    $this->project = sys_get_temp_dir().'/pollora-sqlite-service-'.uniqid();
    mkdir($this->project, 0777, true);
    $this->previousBasePath = app()->basePath();
    app()->setBasePath($this->project);

    // Testbench configures a file of its own
    config(['database.connections.sqlite.database' => null]);

    $this->env = Mockery::mock(EnvironmentFileHandler::class);
    $this->service = new DatabaseService(Mockery::mock(DatabaseConnectionService::class), $this->env);
});

afterEach(function (): void {
    app()->setBasePath($this->previousBasePath);
    (new Filesystem)->deleteDirectory($this->project);
    Container::getInstance()->forgetInstance('log');
});

describe('DatabaseService::configureSqlite', function (): void {
    it('creates the default file and its directory, and writes the connection', function (): void {
        config(['database.connections.sqlite.database' => 'pollora']);
        $this->env->shouldReceive('updateEnvFile')->once()->with([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => 'database/database.sqlite',
        ], true);

        $path = $this->service->configureSqlite();

        expect($path)->toBe('database/database.sqlite')
            ->and(is_file($this->project.'/database/database.sqlite'))->toBeTrue()
            ->and(filesize($this->project.'/database/database.sqlite'))->toBe(0);
    });

    it('ignores a DB_DATABASE that is a MySQL database name, not a file', function (): void {
        config(['database.connections.sqlite.database' => 'wordpress']);
        $this->env->shouldReceive('updateEnvFile')->once()->with(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => 'database/database.sqlite'], true);

        $this->service->configureSqlite();

        expect(is_file($this->project.'/wordpress'))->toBeFalse();
    });

    it('keeps the file the project configured for SQLite', function (string $configured): void {
        config(['database.connections.sqlite.database' => $configured]);
        $this->env->shouldReceive('updateEnvFile')->once()->with(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $configured], true);

        expect($this->service->configureSqlite())->toBe($configured)
            ->and(is_file($this->project.'/'.$configured))->toBeTrue();
    })->with(['storage/site.sqlite', 'storage/site.SQLITE3', 'storage/site.db']);

    it('does not empty a database that already holds data', function (): void {
        config(['database.connections.sqlite.database' => null]);
        mkdir($this->project.'/database');
        file_put_contents($this->project.'/database/database.sqlite', 'SQLite format 3');
        $this->env->shouldReceive('updateEnvFile')->once();

        $this->service->configureSqlite();

        expect(file_get_contents($this->project.'/database/database.sqlite'))->toBe('SQLite format 3');
    });

    it('stops with the reason when the file cannot be created, and leaves the environment alone', function (): void {
        // A file where the directory should be
        file_put_contents($this->project.'/database', 'not a directory');
        $this->env->shouldNotReceive('updateEnvFile');

        expect(fn () => $this->service->configureSqlite())
            ->toThrow(DatabaseConnectionException::class, 'Cannot create the directory of the SQLite database');
    });

    it('stops with the reason when the file itself cannot be written', function (): void {
        mkdir($this->project.'/database/database.sqlite', 0777, true);
        rmdir($this->project.'/database/database.sqlite');
        chmod($this->project.'/database', 0555);
        $this->env->shouldNotReceive('updateEnvFile');

        try {
            expect(fn () => $this->service->configureSqlite())
                ->toThrow(DatabaseConnectionException::class, 'Cannot create the SQLite database file');
        } finally {
            chmod($this->project.'/database', 0755);
        }
    })->skip(fn (): bool => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root writes anywhere');
});
