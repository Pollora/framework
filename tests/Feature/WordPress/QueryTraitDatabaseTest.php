<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\WordPress\Bootstrap;
use Pollora\WordPress\SqliteIntegration;

function contentQueryBootstrap(): Bootstrap
{
    return new Bootstrap(
        Mockery::mock(ConsoleDetectionService::class),
        Mockery::mock(DebugDetectorInterface::class),
        Mockery::mock(Action::class),
    );
}

function contentConnection(array $config, bool $reachable = true): object
{
    $connection = Mockery::mock();
    $connection->shouldReceive('getConfig')->andReturn($config);

    if ($reachable) {
        $connection->shouldReceive('getPdo')->andReturn(new stdClass);
    } else {
        $connection->shouldReceive('getPdo')->andThrow(new PDOException('connection refused'));
    }

    return $connection;
}

/**
 * A project directory holding, as asked, the SQLite file and the plugin that translates WordPress's SQL.
 *
 * Returns the directory; the file is `site.sqlite` in it.
 */
function contentSqliteProject(bool $file, bool $plugin): string
{
    $project = sys_get_temp_dir().'/pollora-qt-'.uniqid();
    mkdir($project.'/plugins/sqlite-database-integration/wp-includes/sqlite', 0777, true);

    if ($file) {
        touch($project.'/site.sqlite');
    }

    if ($plugin) {
        touch($project.'/plugins/sqlite-database-integration/wp-includes/sqlite/db.php');
    }

    SqliteIntegration::useDirectories($project.'/plugins');

    return $project;
}

afterEach(function (): void {
    SqliteIntegration::useDirectories();
});

function contentSchema(callable $expect): void
{
    $schema = Mockery::mock();
    $expect($schema);
    Schema::swap($schema);
}

$mysql = ['driver' => 'mysql', 'host' => 'db', 'username' => 'root', 'password' => 'secret', 'database' => 'shop'];

describe('database detection', function () use ($mysql): void {
    it('is configured when a MySQL connection has every setting and answers', function () use ($mysql): void {
        DB::shouldReceive('connection')->andReturn(contentConnection($mysql));

        expect(contentQueryBootstrap()->isDatabaseConfigured())->toBeTrue();
    });

    it('is not configured for a driver other than MySQL and SQLite', function (string $driver) use ($mysql): void {
        DB::shouldReceive('connection')->andReturn(contentConnection([...$mysql, 'driver' => $driver]));

        expect(contentQueryBootstrap()->isDatabaseConfigured())->toBeFalse();
    })->with(['pgsql', 'sqlsrv']);

    it('is configured when a SQLite connection has its file and the plugin is installed', function (): void {
        $project = contentSqliteProject(file: true, plugin: true);
        DB::shouldReceive('connection')->andReturn(contentConnection(['driver' => 'sqlite', 'database' => $project.'/site.sqlite']));

        expect(contentQueryBootstrap()->isDatabaseConfigured())->toBeTrue();
    });

    it('does not connect to a SQLite file that does not exist yet', function (): void {
        $project = contentSqliteProject(file: false, plugin: true);
        $connection = Mockery::mock();
        $connection->shouldReceive('getConfig')->andReturn(['driver' => 'sqlite', 'database' => $project.'/site.sqlite']);
        $connection->shouldNotReceive('getPdo');
        DB::shouldReceive('connection')->andReturn($connection);

        expect(contentQueryBootstrap()->isDatabaseConfigured())->toBeFalse();
    });

    it('does not load WordPress on SQLite without the plugin that translates its SQL', function (): void {
        $project = contentSqliteProject(file: true, plugin: false);
        $connection = Mockery::mock();
        $connection->shouldReceive('getConfig')->andReturn(['driver' => 'sqlite', 'database' => $project.'/site.sqlite']);
        $connection->shouldNotReceive('getPdo');
        DB::shouldReceive('connection')->andReturn($connection);

        expect(contentQueryBootstrap()->isDatabaseConfigured())->toBeFalse();
    });

    it('is not configured for SQLite in memory', function (): void {
        DB::shouldReceive('connection')->andReturn(contentConnection(['driver' => 'sqlite', 'database' => ':memory:']));

        expect(contentQueryBootstrap()->isDatabaseConfigured())->toBeFalse();
    });

    it('is not configured when a setting is missing', function (string $missing) use ($mysql): void {
        $config = $mysql;
        unset($config[$missing]);
        DB::shouldReceive('connection')->andReturn(contentConnection($config));

        expect(contentQueryBootstrap()->isDatabaseConfigured())->toBeFalse();
    })->with(['host', 'username', 'password', 'database']);

    it('lets the connection error out when the settings are complete but the server is unreachable', function () use ($mysql): void {
        DB::shouldReceive('connection')->andReturn(contentConnection($mysql, reachable: false));

        contentQueryBootstrap()->isDatabaseConfigured();
    })->throws(PDOException::class, 'connection refused');
});

describe('WordPress installation detection', function () use ($mysql): void {
    it('is not installed when the database is not configured', function (): void {
        DB::shouldReceive('connection')->andReturn(contentConnection(['driver' => 'pgsql']));

        expect((new ReflectionMethod(Bootstrap::class, 'isWordPressInstalled'))->invoke(contentQueryBootstrap()))->toBeFalse();
    });

    it('is installed when the options table holds a site URL', function () use ($mysql): void {
        DB::shouldReceive('connection')->andReturn(contentConnection($mysql));
        contentSchema(fn ($schema) => $schema->shouldReceive('hasTable')->with('options')->andReturn(true));
        DB::shouldReceive('table')->with('options')->andReturnSelf();
        DB::shouldReceive('where')->with('option_name', 'siteurl')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(true);

        expect((new ReflectionMethod(Bootstrap::class, 'isWordPressInstalled'))->invoke(contentQueryBootstrap()))->toBeTrue();
    });

    it('is not installed when the options table has no site URL', function () use ($mysql): void {
        DB::shouldReceive('connection')->andReturn(contentConnection($mysql));
        contentSchema(fn ($schema) => $schema->shouldReceive('hasTable')->with('options')->andReturn(true));
        DB::shouldReceive('table')->with('options')->andReturnSelf();
        DB::shouldReceive('where')->with('option_name', 'siteurl')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        expect((new ReflectionMethod(Bootstrap::class, 'isWordPressInstalled'))->invoke(contentQueryBootstrap()))->toBeFalse();
    });

    it('is not installed when the options table does not exist', function () use ($mysql): void {
        DB::shouldReceive('connection')->andReturn(contentConnection($mysql));
        contentSchema(fn ($schema) => $schema->shouldReceive('hasTable')->with('options')->andReturn(false));

        expect((new ReflectionMethod(Bootstrap::class, 'isWordPressInstalled'))->invoke(contentQueryBootstrap()))->toBeFalse();
    });

    it('is not installed when asking the database fails', function () use ($mysql): void {
        DB::shouldReceive('connection')->andReturn(contentConnection($mysql));
        contentSchema(fn ($schema) => $schema->shouldReceive('hasTable')->andThrow(new RuntimeException('gone')));

        expect((new ReflectionMethod(Bootstrap::class, 'isWordPressInstalled'))->invoke(contentQueryBootstrap()))->toBeFalse();
    });
});
