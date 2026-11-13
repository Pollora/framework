<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\WordPress\Bootstrap;

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

    it('is not configured for a driver other than MySQL', function () use ($mysql): void {
        DB::shouldReceive('connection')->andReturn(contentConnection([...$mysql, 'driver' => 'sqlite']));

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
        DB::shouldReceive('connection')->andReturn(contentConnection(['driver' => 'sqlite']));

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
