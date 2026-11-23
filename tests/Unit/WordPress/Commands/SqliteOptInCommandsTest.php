<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Console\Application;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\WordPress\Commands\LaunchPadInstallCommand;
use Pollora\WordPress\Commands\LaunchPadSetupCommand;
use Pollora\WordPress\SqliteIntegration;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A project directory whose `artisan` prints what the installer's second process receives, then exits with 7.
 *
 * @return array{project: string, plugins: string}
 */
function sqliteProject(bool $plugin): array
{
    $project = sys_get_temp_dir().'/pollora-sqlite-cmd-'.uniqid();
    mkdir($project.'/plugins/sqlite-database-integration/wp-includes/sqlite', 0777, true);

    if ($plugin) {
        touch($project.'/plugins/sqlite-database-integration/wp-includes/sqlite/db.php');
    }

    file_put_contents($project.'/artisan', <<<'PHP_WRAP'
    <?php
    echo 'ARGS='.json_encode(array_slice($argv, 1)).PHP_EOL;
    echo 'ENV='.json_encode([getenv('DB_CONNECTION'), getenv('DB_DATABASE')]).PHP_EOL;
    echo 'CWD='.getcwd().PHP_EOL;
    exit(7);
    PHP_WRAP);

    SqliteIntegration::useDirectories($project.'/plugins');

    return ['project' => $project, 'plugins' => $project.'/plugins'];
}

/**
 * @param  array<string, mixed>  $parameters
 * @return array{exit: int, output: string}
 */
function runSqliteInstall(array $parameters, string $project, ?DatabaseService $database = null, bool $interactive = false, bool $installed = false): array
{
    $installation = Mockery::mock(InstallationService::class);
    $installation->shouldReceive('isInstalled')->andReturn($installed);
    $installation->shouldNotReceive('install');

    $database ??= Mockery::mock(DatabaseService::class);

    $container = new class($project) extends Container
    {
        public function __construct(private readonly string $project) {}

        public function runningUnitTests(): bool
        {
            return false;
        }

        public function isLocal(): bool
        {
            return false;
        }

        public function basePath(string $path = ''): string
        {
            return $this->project.($path !== '' ? '/'.$path : '');
        }
    };
    $container->instance('config', new Repository(['app' => ['url' => 'https://acme.test']]));

    $previous = Container::getInstance();
    Container::setInstance($container);

    // Composer sets COMPOSER_DEV_MODE while it runs a script (`composer test:unit` in the CI): the command would then
    // believe it is run by `composer create-project`, where a missing database is not a failure.
    $composerMode = getenv('COMPOSER_DEV_MODE');
    putenv('COMPOSER_DEV_MODE');

    $application = new Application($container, new Dispatcher($container), 'testing');
    $application->setAutoExit(false);

    $command = new LaunchPadInstallCommand($installation, $database);
    $command->setLaravel($container);

    $application->addCommand($command);

    $input = new ArrayInput(['command' => 'pollora:install', ...$parameters]);
    $input->setInteractive($interactive);

    $output = new BufferedOutput;

    try {
        $exit = $application->find('pollora:install')->run($input, $output);
    } finally {
        Container::setInstance($previous);

        if ($composerMode !== false) {
            putenv('COMPOSER_DEV_MODE='.$composerMode);
        }
    }

    return ['exit' => $exit, 'output' => $output->fetch()];
}

afterEach(function (): void {
    SqliteIntegration::useDirectories();
});

describe('pollora:install --db', function (): void {
    it('refuses a database it does not know, before touching anything', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldNotReceive('configureSqlite');

        $result = runSqliteInstall(['--db' => 'oracle'], sqliteProject(plugin: true)['project'], $database);

        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('--db accepts "mysql" or "sqlite"');
    });

    it('stops, with the command to run, when the plugin is not installed', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldNotReceive('configureSqlite');

        $result = runSqliteInstall(['--db' => 'sqlite', '--install' => true], sqliteProject(plugin: false)['project'], $database);

        expect($result['exit'])->toBe(1)
            ->and($result['output'])
            ->toContain('SQLite needs the SQLite Database Integration plugin')
            ->toContain('composer require wp-plugin/sqlite-database-integration')
            ->toContain('public/content/db.php');
    });

    it('prepares the file, warns it is not for production, and installs in a new process', function (): void {
        $project = sqliteProject(plugin: true)['project'];
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('configureSqlite')->once()->andReturn('database/site.sqlite');

        $result = runSqliteInstall([
            '--db' => 'sqlite',
            '--install' => true,
            '--title' => 'Demo',
            '--description' => 'A demo',
            '--admin-user' => 'admin',
            '--admin-email' => 'admin@example.com',
            '--admin-password' => 'secret123',
            '--locale' => 'fr_FR',
            '--public' => 'false',
            '--theme' => 'acme',
        ], $project, $database);

        $args = json_decode(explode('ARGS=', explode("\n", substr($result['output'], strpos($result['output'], 'ARGS=')))[0])[1], true);

        expect($result['exit'])->toBe(7)
            ->and($result['output'])->toContain('development, CI and demos')
            ->and($args)->toBe([
                'pollora:install',
                '--install',
                '--title=Demo',
                '--description=A demo',
                '--admin-user=admin',
                '--admin-email=admin@example.com',
                '--admin-password=secret123',
                '--locale=fr_FR',
                '--public=false',
                '--theme=acme',
                '--no-interaction',
            ])
            ->and($result['output'])->toContain('ENV=["sqlite","database\/site.sqlite"]')
            ->and($result['output'])->toContain('CWD='.realpath($project));
    });

    it('forwards only the options it was given, and never --db, which would loop', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('configureSqlite')->once()->andReturn('database/database.sqlite');

        $result = runSqliteInstall(['--db' => 'sqlite'], sqliteProject(plugin: true)['project'], $database);

        expect($result['output'])->toContain('ARGS=["pollora:install","--no-interaction"]')
            ->not->toContain('--db');
    });

    it('does not pass --no-interaction on to a terminal session', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('configureSqlite')->once()->andReturn('database/database.sqlite');

        $result = runSqliteInstall(['--db' => 'sqlite', '--install' => true], sqliteProject(plugin: true)['project'], $database, interactive: true);

        expect($result['output'])->not->toContain('--no-interaction');
    })->skip(fn (): bool => ! function_exists('posix_isatty') || posix_isatty(STDOUT), 'a terminal would hand its TTY to the child process');

    it('leaves an installed site alone', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldNotReceive('configureSqlite');

        $result = runSqliteInstall(['--db' => 'sqlite'], sqliteProject(plugin: true)['project'], $database, installed: true);

        expect($result['exit'])->toBe(0)
            ->and($result['output'])->toContain('already installed');
    });

    it('accepts --db=mysql and behaves as before', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('isConfigured')->andReturn(false);
        $database->shouldNotReceive('configureSqlite');

        $result = runSqliteInstall(['--db' => 'mysql'], sqliteProject(plugin: true)['project'], $database);

        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('DB_CONNECTION=mysql');
    });

    it('mentions --db=sqlite to a project without a database', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('isConfigured')->andReturn(false);

        $result = runSqliteInstall([], sqliteProject(plugin: true)['project'], $database);

        expect($result['output'])->toContain('php artisan pollora:install --db=sqlite');
    });
});

/**
 * @param  array<string, mixed>  $parameters
 * @return array{exit: int, output: string}
 */
function runSqliteSetup(array $parameters, DatabaseService $database, string $appKey = ''): array
{
    $container = new class extends Container
    {
        public function runningUnitTests(): bool
        {
            return false;
        }

        public function isLocal(): bool
        {
            return false;
        }
    };
    $container->instance('config', new Repository(['app' => ['key' => $appKey]]));

    $previous = Container::getInstance();
    Container::setInstance($container);

    $application = new Application($container, new Dispatcher($container), 'testing');
    $application->setAutoExit(false);

    $command = new LaunchPadSetupCommand($database);
    $command->setLaravel($container);

    $application->addCommand($command);
    $application->addCommand(new #[Signature('key:generate')] class extends Command
    {
        public function handle(): int
        {
            $this->line('key generated');

            return self::SUCCESS;
        }
    });

    $input = new ArrayInput(['command' => 'pollora:env:setup', ...$parameters]);
    $input->setInteractive(false);

    $output = new BufferedOutput;

    try {
        $exit = $application->find('pollora:env:setup')->run($input, $output);
    } finally {
        Container::setInstance($previous);
    }

    return ['exit' => $exit, 'output' => $output->fetch()];
}

describe('pollora:env:setup --db', function (): void {
    it('refuses a database it does not know', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldNotReceive('configureSqlite');

        $result = runSqliteSetup(['--db' => 'oracle'], $database);

        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('--db accepts "mysql" or "sqlite"');
    });

    it('sets SQLite up without a terminal and generates the missing application key', function (): void {
        sqliteProject(plugin: true);
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('configureSqlite')->once()->andReturn('database/database.sqlite');
        $database->shouldNotReceive('isConfigured');

        $result = runSqliteSetup(['--db' => 'sqlite'], $database);

        expect($result['exit'])->toBe(0)
            ->and($result['output'])
            ->toContain('development, CI and demos')
            ->toContain('key generated')
            ->toContain('Environment configuration completed successfully!')
            ->not->toContain('SQLite Database Integration plugin: composer require');
    });

    it('keeps the application key of a project that already has one', function (): void {
        sqliteProject(plugin: true);
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('configureSqlite')->once()->andReturn('database/database.sqlite');

        $result = runSqliteSetup(['--db' => 'sqlite'], $database, appKey: 'base64:abc');

        expect($result['output'])->not->toContain('key generated');
    });

    it('warns, without failing composer, when the plugin is not there yet', function (): void {
        sqliteProject(plugin: false);
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('configureSqlite')->once()->andReturn('database/database.sqlite');

        $result = runSqliteSetup(['--db' => 'sqlite'], $database);

        expect($result['exit'])->toBe(0)
            ->and($result['output'])->toContain('composer require wp-plugin/sqlite-database-integration');
    });

    it('fails with the reason when the SQLite file cannot be prepared', function (): void {
        sqliteProject(plugin: true);
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('configureSqlite')->andThrow(new RuntimeException('read-only disk'));

        $result = runSqliteSetup(['--db' => 'sqlite'], $database);

        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('read-only disk');
    });

    it('tells a project without a terminal that --db=sqlite exists', function (): void {
        $database = Mockery::mock(DatabaseService::class);
        $database->shouldReceive('isConfigured')->andReturn(false);

        $result = runSqliteSetup([], $database);

        expect($result['output'])->toContain('php artisan pollora:env:setup --db=sqlite');
    });
});
