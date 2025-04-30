<?php

declare(strict_types=1);

namespace Pollora\WordPress\Commands;

use Illuminate\Console\Command;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\DTO\InstallationConfig;
use Pollora\Services\WordPress\Installation\InstallationService;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class LaunchPadInstallCommand extends Command
{
    protected $signature = 'pollora:install';

    protected $description = 'Install and configure WordPress';

    public function __construct(
        private readonly InstallationService $installationService,
        private readonly DatabaseService $databaseService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            if ($this->installationService->isInstalled()) {
                info('WordPress is already installed.');

                return self::SUCCESS;
            }

            if (! $this->databaseService->isConfigured()) {
                info('Database not configured. Please run: php artisan wp:env-setup');

                return self::FAILURE;
            }

            $this->installWordPress();

            return self::SUCCESS;

        } catch (\Throwable $e) {
            error($e->getMessage());
            $this->handleError($e);

            return self::FAILURE;
        }
    }

    private function installWordPress(): void
    {
        info('Starting WordPress installation...');

        $config = InstallationConfig::fromPrompts();

        $this->installationService->install($config);

        $this->runMigrations();

        $this->displaySuccessMessage();
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
        info('3. Install your preferred theme and plugins');
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
