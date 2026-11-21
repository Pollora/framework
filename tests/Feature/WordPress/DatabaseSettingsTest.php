<?php

declare(strict_types=1);

use Pollora\WordPress\DatabaseSettings;

$mysql = ['driver' => 'mysql', 'host' => 'db', 'username' => 'root', 'password' => '', 'database' => 'shop'];

describe('DatabaseSettings::isComplete', function () use ($mysql): void {
    it('accepts a MySQL connection with every setting, an empty password included', function () use ($mysql): void {
        expect(DatabaseSettings::isComplete($mysql))->toBeTrue();
    });

    it('rejects a MySQL connection missing a setting', function (string $missing) use ($mysql): void {
        unset($mysql[$missing]);

        expect(DatabaseSettings::isComplete($mysql))->toBeFalse();
    })->with(['host', 'username', 'password', 'database']);

    it('accepts a SQLite connection with a database file', function (): void {
        expect(DatabaseSettings::isComplete(['driver' => 'sqlite', 'database' => '/data/site.sqlite']))->toBeTrue();
    });

    it('needs no host, user or password for SQLite', function (): void {
        expect(DatabaseSettings::isComplete(['driver' => 'sqlite', 'database' => 'database/site.sqlite']))->toBeTrue();
    });

    it('rejects a SQLite connection without a usable file', function (mixed $database): void {
        expect(DatabaseSettings::isComplete(['driver' => 'sqlite', 'database' => $database]))->toBeFalse();
    })->with([null, '', ':memory:', 'file::memory:?cache=shared', 42]);

    it('rejects a SQLite connection with no database key', function (): void {
        expect(DatabaseSettings::isComplete(['driver' => 'sqlite']))->toBeFalse();
    });

    it('rejects every other driver, even with every MySQL setting', function (?string $driver) use ($mysql): void {
        expect(DatabaseSettings::isComplete([...$mysql, 'driver' => $driver]))->toBeFalse();
    })->with(['pgsql', 'mariadb', 'sqlsrv', null]);

    it('rejects a connection with no driver at all', function (): void {
        expect(DatabaseSettings::isComplete(['host' => 'db', 'username' => 'u', 'password' => 'p', 'database' => 'd']))->toBeFalse();
    });
});

describe('DatabaseSettings::isSqlite', function (): void {
    it('recognises the sqlite driver only', function (): void {
        expect(DatabaseSettings::isSqlite(['driver' => 'sqlite']))->toBeTrue()
            ->and(DatabaseSettings::isSqlite(['driver' => 'mysql']))->toBeFalse()
            ->and(DatabaseSettings::isSqlite([]))->toBeFalse();
    });
});

describe('DatabaseSettings::sqlitePath', function (): void {
    it('keeps an absolute Unix path', function (): void {
        expect(DatabaseSettings::sqlitePath(['database' => '/var/data/site.sqlite']))->toBe('/var/data/site.sqlite');
    });

    it('keeps an absolute Windows path', function (string $path): void {
        expect(DatabaseSettings::sqlitePath(['database' => $path]))->toBe($path);
    })->with(['C:\\data\\site.sqlite', 'D:/data/site.sqlite']);

    it('reads a relative path from the project root, as Laravel does', function (): void {
        expect(DatabaseSettings::sqlitePath(['database' => 'database/site.sqlite']))->toBe(base_path('database/site.sqlite'));
    });

    it('has no file for an empty, missing or in-memory database', function (mixed $database): void {
        expect(DatabaseSettings::sqlitePath(['database' => $database]))->toBeNull();
    })->with([null, '', ':memory:', 7]);
});
