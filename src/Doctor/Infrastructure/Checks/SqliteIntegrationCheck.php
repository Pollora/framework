<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\WordPress\DatabaseSettings;
use Pollora\WordPress\SqliteIntegration;

/**
 * On SQLite, the SQLite Database Integration plugin is there to translate WordPress's SQL.
 *
 * WordPress writes MySQL. Without the plugin's `db.php` drop-in, a SQLite
 * connection leaves WordPress unable to load, and the site answers 503 with
 * no hint of why. Only a project whose default connection is SQLite is
 * concerned; MySQL projects skip this check.
 */
final readonly class SqliteIntegrationCheck implements CheckInterface
{
    public function id(): string
    {
        return 'sqlite-integration';
    }

    public function label(): string
    {
        return 'SQLite Database Integration plugin';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $connection = (array) config('database.connections.'.config('database.default'), []);

        if (! DatabaseSettings::isSqlite($connection)) {
            return CheckResult::skipped('The default connection is not SQLite.');
        }

        if (! SqliteIntegration::isInstalled()) {
            return CheckResult::error(
                'The default connection is SQLite, but the SQLite Database Integration plugin is not installed.',
                ['Expected: '.SqliteIntegration::loaderPath()],
                'composer require '.SqliteIntegration::PACKAGE,
            );
        }

        $dropIn = SqliteIntegration::dropInPath();

        if (! is_file($dropIn)) {
            return CheckResult::error(
                'The plugin is installed, but the project has no db.php drop-in to load it.',
                ['Expected: '.$dropIn],
                "Add public/content/db.php: when DB_CONNECTION=sqlite, define DB_ENGINE and DB_PATH, then require the plugin's ".SqliteIntegration::LOADER,
            );
        }

        $file = DatabaseSettings::sqlitePath($connection);
        $public = realpath(public_path());

        if ($file !== null && $public !== false && str_starts_with(realpath(dirname($file)).'/', $public.'/')) {
            return CheckResult::error(
                'The SQLite database file is under the public directory: the web server can hand it out.',
                ['File: '.$file],
                'Move it out of public/, for example DB_DATABASE=database/database.sqlite',
            );
        }

        return CheckResult::ok(
            'The SQLite Database Integration plugin is installed (development, CI and demos only: use MySQL or MariaDB in production).',
        );
    }
}
