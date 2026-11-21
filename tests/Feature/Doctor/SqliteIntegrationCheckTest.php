<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\SqliteIntegrationCheck;
use Pollora\WordPress\SqliteIntegration;

beforeEach(function (): void {
    $this->project = sys_get_temp_dir().'/pollora-sqlite-check-'.uniqid();
    mkdir($this->project.'/public/content/plugins', 0777, true);
    mkdir($this->project.'/database', 0777, true);
    $this->previousBasePath = app()->basePath();
    app()->setBasePath($this->project);
    app()->usePublicPath($this->project.'/public');

    SqliteIntegration::useDirectories($this->project.'/public/content/plugins', $this->project.'/public/content');

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => 'database/database.sqlite'],
    ]);

    $this->installPlugin = function (): void {
        $loader = SqliteIntegration::loaderPath();
        mkdir(dirname($loader), 0777, true);
        touch($loader);
    };
});

afterEach(function (): void {
    SqliteIntegration::useDirectories();
    app()->setBasePath($this->previousBasePath);
    (new Filesystem)->deleteDirectory($this->project);
});

describe('SQLite Database Integration plugin check', function (): void {
    it('is skipped on a MySQL project', function (): void {
        config(['database.default' => 'mysql', 'database.connections.mysql' => ['driver' => 'mysql']]);

        $result = (new SqliteIntegrationCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('skipped');
    });

    it('fails, with the command to run, when the plugin is not installed', function (): void {
        $result = (new SqliteIntegrationCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->summary)->toContain('plugin is not installed')
            ->and($result->fix)->toBe('composer require wp-plugin/sqlite-database-integration')
            ->and($result->details)->toBe(['Expected: '.SqliteIntegration::loaderPath()]);
    });

    it('fails when the plugin has no db.php drop-in to load it', function (): void {
        ($this->installPlugin)();

        $result = (new SqliteIntegrationCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->summary)->toContain('no db.php drop-in')
            ->and($result->details)->toBe(['Expected: '.$this->project.'/public/content/db.php'])
            ->and($result->fix)->toContain('DB_ENGINE')->toContain('wp-includes/sqlite/db.php');
    });

    it('passes when the plugin and the drop-in are there, and says it is not for production', function (): void {
        ($this->installPlugin)();
        touch($this->project.'/public/content/db.php');

        $result = (new SqliteIntegrationCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('ok')
            ->and($result->summary)->toContain('MySQL or MariaDB in production');
    });

    it('fails when the database file sits under the public directory', function (): void {
        ($this->installPlugin)();
        touch($this->project.'/public/content/db.php');
        config(['database.connections.sqlite.database' => 'public/content/site.sqlite']);

        $result = (new SqliteIntegrationCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->summary)->toContain('under the public directory')
            ->and($result->details)->toBe(['File: '.$this->project.'/public/content/site.sqlite']);
    });

    it('does not mistake a sibling directory sharing the public prefix for the public directory', function (): void {
        ($this->installPlugin)();
        touch($this->project.'/public/content/db.php');
        mkdir($this->project.'/public-data');
        config(['database.connections.sqlite.database' => 'public-data/site.sqlite']);

        expect((new SqliteIntegrationCheck)->run(RunContext::Console)->status->value)->toBe('ok');
    });

    it('is named, and runs in the console and in HTTP', function (): void {
        $check = new SqliteIntegrationCheck;

        expect($check->id())->toBe('sqlite-integration')
            ->and($check->label())->toBe('SQLite Database Integration plugin')
            ->and($check->runsIn())->toBe([RunContext::Console, RunContext::Http]);
    });
});
