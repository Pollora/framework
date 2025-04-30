<?php

declare(strict_types=1);

namespace Pollora\WordPress\Commands;

namespace Pollora\WordPress\Commands;

use Illuminate\Console\Command;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class LaunchPadSetupCommand extends Command
{
    protected $signature = 'pollora:env-setup';

    protected $description = 'Configure environment for WordPress installation';

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            if ($this->databaseService->isConfigured()) {
                info('Database is already configured.');

                return self::SUCCESS;
            }

            $config = DatabaseConfig::fromPrompts();

            $this->databaseService->configure($config);

            $this->call('key:generate');

            info('Environment configuration completed successfully!');
            info('You can now run: php artisan wp:install');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            error($e->getMessage());

            return self::FAILURE;
        }
    }
}
