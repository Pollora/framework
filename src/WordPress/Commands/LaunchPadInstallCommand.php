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
use Pollora\Services\WordPress\Installation\WordPressInstallationException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[Description('Install and configure WordPress')]
#[Signature('pollora:install
        {--install : Suppress informational output for automated runs}
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
        private readonly DatabaseService $databaseService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $installMode = $this->option('install');

        try {
            if ($this->installationService->isInstalled()) {
                if (! $installMode) {
                    info('WordPress is already installed.');
                }

                return self::SUCCESS;
            }

            if (! $this->databaseService->isConfigured()) {
                if (! $installMode) {
                    info('Application environment is not configured. Aborting.');
                }

                return self::FAILURE;
            }

            $this->installWordPress($installMode);

            return self::SUCCESS;

        } catch (\Throwable $throwable) {
            error($throwable->getMessage());

            $this->handleError($throwable);

            return self::FAILURE;
        }
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
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

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
