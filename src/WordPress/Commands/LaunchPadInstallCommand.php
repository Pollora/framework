<?php

declare(strict_types=1);

namespace Pollora\WordPress\Commands;

use Illuminate\Console\Command;
use Pollora\Services\WordPress\Installation\DatabaseConnectionException;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\DTO\InstallationConfig;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\Services\WordPress\Installation\WordPressInstallationException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class LaunchPadInstallCommand extends Command
{
    /**
     * The theme scaffolded when the caller named none and cannot be asked.
     */
    private const DEFAULT_THEME = 'default';

    protected $signature = 'pollora:install
        {--install : Suppress informational output for automated runs}
        {--title= : Site title}
        {--description= : Site description}
        {--admin-user= : Admin username}
        {--admin-email= : Admin email}
        {--admin-password= : Admin password}
        {--locale= : Site locale (e.g. en_US, fr_FR)}
        {--public= : Allow search engine indexing (true/false)}
        {--theme= : Name of the theme to scaffold once WordPress is installed}';

    protected $description = 'Install and configure WordPress';

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

        $config = InstallationConfig::fromPrompts(
            title: $this->option('title'),
            description: $this->option('description'),
            adminUser: $this->option('admin-user'),
            adminEmail: $this->option('admin-email'),
            adminPassword: $this->option('admin-password'),
            locale: $this->option('locale'),
            isPublic: $this->option('public') !== null ? filter_var($this->option('public'), FILTER_VALIDATE_BOOLEAN) : null,
        );

        $this->installationService->install($config);

        $this->runMigrations();

        $this->installTheme();

        $this->displaySuccessMessage();
    }

    /**
     * Scaffold a theme once WordPress is installed.
     *
     * The name matters here. `pollora:make-theme` requires one, and a nested
     * call inherits only an explicit --no-interaction flag, never a
     * non-interactive input Symfony detected on its own — CI, a piped stdin, a
     * provisioning script. So `pollora:install --install --no-interaction`
     * installed WordPress and then died on
     *
     *   Not enough arguments (missing: "name").
     *
     * after the site was already in place: an exit code of 1 for a successful
     * install, and a message naming a command the caller never typed.
     */
    private function installTheme(): void
    {
        $arguments = [];
        $theme = $this->option('theme');

        if (is_string($theme) && $theme !== '') {
            $arguments['name'] = $theme;
        }

        if (! $this->input->isInteractive()) {
            $arguments['name'] ??= self::DEFAULT_THEME;
            $arguments['--no-interaction'] = true;
        }

        $this->call('pollora:make-theme', $arguments);
    }

    public function runMigrations(): void
    {
        info('Running migration.');
        $this->call('migrate');

        info('Migration completed successfully.');
        info('WordPress has been successfully installed!');

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
            error('Database connection failed. Please check your credentials and run: php artisan wp:env-setup');
        } elseif ($e instanceof WordPressInstallationException) {
            error('WordPress installation failed. Please check the error message and try again.');
        }

        if (app()->isLocal()) {
            error('Full error: '.$e->getMessage());
            error('Stack trace: '.$e->getTraceAsString());
        }
    }
}
