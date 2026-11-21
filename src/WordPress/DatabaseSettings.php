<?php

declare(strict_types=1);

namespace Pollora\WordPress;

/**
 * What Pollora asks of a Laravel database connection before it loads WordPress.
 *
 * Two drivers qualify: `mysql`, WordPress's own, and `sqlite`, which needs the
 * SQLite Database Integration plugin's `db.php` drop-in to translate
 * WordPress's SQL. SQLite is for development, CI and demos; production stays
 * on MySQL or MariaDB.
 *
 * Kept in one place so the bootstrap, the `wpdb` proxy and the setup page
 * agree on what "configured" means.
 */
final class DatabaseSettings
{
    public const string MYSQL = 'mysql';

    public const string SQLITE = 'sqlite';

    /**
     * Whether the connection carries every setting its driver needs.
     *
     * Reads the configuration only: it neither connects nor looks for a file.
     *
     * @param  array<string, mixed>  $config  A Laravel connection configuration
     */
    public static function isComplete(array $config): bool
    {
        return match ($config['driver'] ?? null) {
            self::MYSQL => isset($config['host'], $config['username'], $config['password'], $config['database']),
            self::SQLITE => self::sqlitePath($config) !== null,
            default => false,
        };
    }

    /**
     * Whether the connection is a SQLite one.
     *
     * @param  array<string, mixed>  $config  A Laravel connection configuration
     */
    public static function isSqlite(array $config): bool
    {
        return ($config['driver'] ?? null) === self::SQLITE;
    }

    /**
     * The SQLite database file of a connection, or null when it has none.
     *
     * A relative path is read from the project root, as Laravel does. An empty
     * path and the in-memory database are not files WordPress could share
     * between two requests, so they count as no file.
     *
     * @param  array<string, mixed>  $config  A Laravel connection configuration
     */
    public static function sqlitePath(array $config): ?string
    {
        $database = $config['database'] ?? null;

        if (! is_string($database) || $database === '' || str_contains($database, ':memory:')) {
            return null;
        }

        if (str_starts_with($database, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $database) === 1) {
            return $database;
        }

        return base_path($database);
    }
}
