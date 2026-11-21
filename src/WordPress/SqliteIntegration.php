<?php

declare(strict_types=1);

namespace Pollora\WordPress;

/**
 * Where the SQLite Database Integration plugin and its drop-in live.
 *
 * WordPress speaks MySQL. On SQLite, the plugin's `db.php` translates each
 * query, and the project's `wp-content/db.php` drop-in loads it in place of
 * `wpdb`. Development, CI and demos only: production runs on MySQL or MariaDB.
 */
final class SqliteIntegration
{
    /** The plugin's directory name on wordpress.org. */
    public const string PLUGIN = 'sqlite-database-integration';

    /** The Composer package that installs it (wp-packages.org). */
    public const string PACKAGE = 'wp-plugin/sqlite-database-integration';

    /** The plugin file a `db.php` drop-in loads, relative to the plugin directory. */
    public const string LOADER = 'wp-includes/sqlite/db.php';

    private static ?string $pluginsDirectory = null;

    private static ?string $contentDirectory = null;

    /**
     * Read the plugin and the drop-in from these directories instead of WordPress's constants.
     *
     * Constants cannot be redefined, so a test needs another way to place the files.
     * Call it with no argument to go back to the constants.
     *
     * @internal
     */
    public static function useDirectories(?string $plugins = null, ?string $content = null): void
    {
        self::$pluginsDirectory = $plugins;
        self::$contentDirectory = $content;
    }

    /**
     * Absolute path of the plugin's loader file.
     */
    public static function loaderPath(): string
    {
        $plugins = self::$pluginsDirectory ?? match (true) {
            defined('WP_PLUGIN_DIR') => (string) constant('WP_PLUGIN_DIR'),
            // WordPress defines WP_PLUGIN_DIR after Pollora's bootstrap asks
            defined('WP_CONTENT_DIR') => constant('WP_CONTENT_DIR').'/plugins',
            default => public_path('content/plugins'),
        };

        return rtrim($plugins, '/').'/'.self::PLUGIN.'/'.self::LOADER;
    }

    /**
     * Whether the plugin is installed.
     */
    public static function isInstalled(): bool
    {
        return is_file(self::loaderPath());
    }

    /**
     * Absolute path of the project's `db.php` drop-in.
     */
    public static function dropInPath(): string
    {
        $content = self::$contentDirectory ?? (defined('WP_CONTENT_DIR') ? (string) constant('WP_CONTENT_DIR') : public_path('content'));

        return rtrim($content, '/').'/db.php';
    }
}
