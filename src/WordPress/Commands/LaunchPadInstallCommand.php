<?php

declare(strict_types=1);

namespace Pollora\WordPress\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Services\WordPress\Installation\DatabaseConnectionException;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\DTO\InstallationConfig;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\Services\WordPress\Installation\SaltsService;
use Pollora\Services\WordPress\Installation\WordPressInstallationException;
use Pollora\WordPress\AppUrl;
use Pollora\WordPress\SqliteIntegration;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[Description('Install and configure WordPress')]
#[Signature('pollora:install
        {--install : Suppress informational output for automated runs}
        {--db= : Database to set up: mysql (the default, configured by pollora:env:setup) or sqlite (development, CI and demos only)}
        {--title= : Site title}
        {--description= : Site description}
        {--admin-user= : Admin username}
        {--admin-email= : Admin email}
        {--admin-password= : Admin password}
        {--locale= : Site locale (e.g. en_US, fr_FR)}
        {--public= : Allow search engine indexing (true/false)}
        {--theme= : Name of the theme to generate (defaults to "default" from pollora/theme-default without interaction)}')]
class LaunchPadInstallCommand extends Command
{
    /**
     * Name of the theme generated when the install cannot prompt for one.
     *
     * Matches WP_DEFAULT_THEME, which WordPress activates on install.
     */
    private const string DEFAULT_THEME = 'default';

    public function __construct(
        private readonly InstallationService $installationService,
        private readonly DatabaseService $databaseService,
        // Not optional: the container keeps a default value rather than resolving the class, and nothing would be generated
        private readonly SaltsService $salts
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $installMode = $this->option('install');
        $db = $this->option('db');

        if ($db !== null && ! in_array($db, ['mysql', 'sqlite'], true)) {
            error('--db accepts "mysql" or "sqlite".');

            return self::FAILURE;
        }

        try {
            if ($this->installationService->isInstalled()) {
                if (! $installMode) {
                    info('WordPress is already installed.');
                }

                return self::SUCCESS;
            }

            if ($db === 'sqlite') {
                return $this->installOnSqlite();
            }

            if (! $this->databaseService->isConfigured()) {
                return $this->reportMissingDatabase();
            }

            $this->generateMissingSalts();
            $this->installWordPress($installMode);

            return self::SUCCESS;

        } catch (\Throwable $throwable) {
            error($throwable->getMessage());

            $this->handleError($throwable);

            return self::FAILURE;
        }
    }

    /**
     * A new site gets keys and salts of its own, once: nobody is logged in yet. Those that are there are kept.
     */
    private function generateMissingSalts(): void
    {
        if (! $this->salts->canWrite()) {
            return;
        }

        $missing = $this->salts->missing();

        if ($missing !== []) {
            $this->salts->generate($missing);
            info(sprintf('%d WordPress key(s) and salt(s) generated in .env.', count($missing)));
        }
    }

    /**
     * Without a database there is nothing to install.
     *
     * Run by hand or in a deployment, that is a failure. Run by Composer
     * (`post-create-project-cmd` of the skeleton), it is the normal state of a
     * project nobody has configured yet: say what to do and let
     * `composer create-project` finish (Pollora/pollora#78).
     */
    private function reportMissingDatabase(): int
    {
        $runByComposer = $this->runByComposer();

        $message = 'The database is not configured, so WordPress cannot be installed yet.';

        if ($runByComposer) {
            warning($message);
        } else {
            error($message);
        }

        $this->line('  Set DB_CONNECTION=mysql (or mariadb) and the DB_* values in .env, or run: php artisan pollora:env:setup');
        $this->line('  For a development database without a server: php artisan pollora:install --db=sqlite');
        $this->line('  Then: php artisan pollora:install (php artisan pollora:doctor checks the project)');

        return $runByComposer ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Install on a SQLite file: development, CI and demos, never production.
     *
     * Writes the connection to `.env`, then runs the installation in a new
     * process: Laravel read its configuration, and WordPress its database
     * drop-in, before this command could change them. The new process gets
     * the connection in its environment too, since a variable the shell
     * already holds (Docker, DDEV) wins over `.env`.
     */
    private function installOnSqlite(): int
    {
        if (! SqliteIntegration::isInstalled()) {
            error('SQLite needs the SQLite Database Integration plugin, which is not installed.');
            $this->line('  Run: composer require '.SqliteIntegration::PACKAGE);
            $this->line("  The project's public/content/db.php must load it when DB_CONNECTION=sqlite (the skeleton does).");

            return self::FAILURE;
        }

        $database = $this->databaseService->configureSqlite();

        warning('SQLite is for development, CI and demos. Use MySQL or MariaDB in production.');

        $process = new Process(
            [PHP_BINARY, base_path('artisan'), 'pollora:install', ...$this->forwardedArguments()],
            base_path(),
            ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database],
            timeout: null,
        );

        if ($this->input->isInteractive() && Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }

    /**
     * The options of this run, minus --db, for the process that does the install.
     *
     * @return list<string>
     */
    private function forwardedArguments(): array
    {
        $arguments = [];

        if ($this->option('install')) {
            $arguments[] = '--install';
        }

        foreach (['title', 'description', 'admin-user', 'admin-email', 'admin-password', 'locale', 'public', 'theme'] as $option) {
            $value = $this->option($option);

            if (is_string($value)) {
                $arguments[] = '--'.$option.'='.$value;
            }
        }

        if (! $this->input->isInteractive()) {
            $arguments[] = '--no-interaction';
        }

        return $arguments;
    }

    /**
     * Whether a Composer script runs this command: Composer sets
     * COMPOSER_DEV_MODE for every script it runs.
     */
    protected function runByComposer(): bool
    {
        return getenv('COMPOSER_DEV_MODE') !== false;
    }

    private function installWordPress(bool $silent = false): void
    {
        if (! $silent) {
            info('Starting WordPress installation...');
        }

        $options = [
            'title' => $this->option('title'),
            'description' => $this->option('description'),
            'adminUser' => $this->option('admin-user'),
            'adminEmail' => $this->option('admin-email'),
            'adminPassword' => $this->option('admin-password'),
            'locale' => $this->option('locale'),
            'isPublic' => $this->option('public') !== null ? filter_var($this->option('public'), FILTER_VALIDATE_BOOLEAN) : null,
        ];

        $config = $this->input->isInteractive()
            ? InstallationConfig::fromPrompts(...$options)
            : InstallationConfig::withDefaults($this->projectName(), config('app.url'), ...$options);

        $this->installationService->install($config);

        if ($options['adminPassword'] === null && ! $this->input->isInteractive()) {
            // Generated here and stored hashed: this is the only time it is shown.
            warning(sprintf('Admin account: %s / %s — generated, shown only once.', $config->adminUser, $config->adminPassword));
        }

        $this->runMigrations();

        $this->installTheme();

        $this->displaySuccessMessage();
    }

    /**
     * The project's name, for a site title nobody was asked for.
     *
     * The first label of the application's host (`acme.ddev.site` → `acme`):
     * under DDEV or Docker the project always lives in `/var/www/html`, whose
     * directory name says nothing. The directory name only without a real host.
     */
    private function projectName(): string
    {
        $host = AppUrl::host((string) config('app.url'));

        if (is_string($host) && str_contains($host, '.')) {
            return explode('.', $host)[0];
        }

        return basename(base_path());
    }

    private function installTheme(): void
    {
        $arguments = [];
        $theme = $this->option('theme');

        if (is_string($theme) && $theme !== '') {
            $arguments['name'] = $theme;
        }

        if (! $this->input->isInteractive()) {
            // A nested call only inherits an explicit --no-interaction flag, not a
            // non-interactive input detected by Symfony (CI, piped stdin)
            $arguments['name'] ??= self::DEFAULT_THEME;
            $arguments['--no-interaction'] = true;
        }

        // Installing a site is the intent to use the theme it generates.
        $arguments['--activate'] = true;

        $this->call('pollora:make:theme', $arguments);
    }

    public function runMigrations(): void
    {
        info('Running migration.');

        // Installing is the intent to migrate: without --force, migrate asks for
        // confirmation in production and cancels when it cannot prompt
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            throw new WordPressInstallationException('Database migrations failed.');
        }

        info('Migration completed successfully.');
    }

    private function displaySuccessMessage(): void
    {
        info('WordPress has been successfully installed!');
        info('Next steps:');
        info('1. Access your WordPress admin at: '.admin_url());
        info('2. Start customizing your site');
        info('3. Enjoy Pollora :)');
    }

    private function handleError(\Throwable $e): void
    {
        if ($e instanceof DatabaseConnectionException) {
            error('Database connection failed. Please check your credentials and run: php artisan pollora:env:setup');
        } elseif ($e instanceof WordPressInstallationException) {
            error('WordPress installation failed. Please check the error message and try again.');
        }

        if (app()->isLocal()) {
            error('Full error: '.$e->getMessage());
            error('Stack trace: '.$e->getTraceAsString());
        }
    }
}
