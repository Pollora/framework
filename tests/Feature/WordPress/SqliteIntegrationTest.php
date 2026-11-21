<?php

declare(strict_types=1);

use Pollora\WordPress\SqliteIntegration;

afterEach(function (): void {
    SqliteIntegration::useDirectories();
});

describe('SqliteIntegration', function (): void {
    it('looks for the plugin loader in the plugins directory', function (): void {
        SqliteIntegration::useDirectories('/srv/plugins/');

        expect(SqliteIntegration::loaderPath())->toBe('/srv/plugins/sqlite-database-integration/wp-includes/sqlite/db.php');
    });

    it('knows the package that installs the plugin', function (): void {
        expect(SqliteIntegration::PACKAGE)->toBe('wp-plugin/sqlite-database-integration');
    });

    it('is installed when the loader file exists, and only then', function (): void {
        $plugins = sys_get_temp_dir().'/pollora-sqlite-'.uniqid();

        SqliteIntegration::useDirectories($plugins);
        expect(SqliteIntegration::isInstalled())->toBeFalse();

        mkdir($plugins.'/sqlite-database-integration/wp-includes/sqlite', 0777, true);
        file_put_contents(SqliteIntegration::loaderPath(), '<?php');

        expect(SqliteIntegration::isInstalled())->toBeTrue();

        unlink(SqliteIntegration::loaderPath());
        rmdir($plugins.'/sqlite-database-integration/wp-includes/sqlite');
        rmdir($plugins.'/sqlite-database-integration/wp-includes');
        rmdir($plugins.'/sqlite-database-integration');
        rmdir($plugins);
    });

    it('finds the drop-in in the content directory', function (): void {
        SqliteIntegration::useDirectories(content: '/srv/content/');

        expect(SqliteIntegration::dropInPath())->toBe('/srv/content/db.php');
    });

    it('falls back to the project when WordPress defines no constant', function (): void {
        // Constants are process-wide: the fallback shows only where no other test defined them
        if (defined('WP_PLUGIN_DIR') || defined('WP_CONTENT_DIR')) {
            expect(SqliteIntegration::loaderPath())->toEndWith('/sqlite-database-integration/wp-includes/sqlite/db.php');

            return;
        }

        expect(SqliteIntegration::loaderPath())->toBe(public_path('content/plugins').'/sqlite-database-integration/wp-includes/sqlite/db.php')
            ->and(SqliteIntegration::dropInPath())->toBe(public_path('content').'/db.php');
    });
});
