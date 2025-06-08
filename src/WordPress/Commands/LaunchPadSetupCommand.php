<?php

declare(strict_types=1);

namespace Pollora\WordPress\Commands;

use Illuminate\Console\Command;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class LaunchPadSetupCommand extends Command
{
    protected $signature = 'pollora:env-setup {--install : Suppress some informational output}';

    protected $description = 'Configure environment for WordPress installation';

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $install = $this->option('install');

        try {
            if ($this->databaseService->isConfigured()) {
                if (! $install) {
                    info('Database is already configured.');
                }

                return self::SUCCESS;
            }

            if ($install) {
                info("
  ____       _ _                 \n |  _ \\ ___ | | | ___  _ __ __ _ \n | |_) / _ \\| | |/ _ \\| '__/ _` |\n |  __/ (_) | | | (_) | | | (_| |\n |_|   \\___/|_|_|\\___/|_|  \\__,_|\n                                 \n\nWelcome to the Pollora installation!\nLet's install WordPress and set up your project...\n");
            }

            $config = DatabaseConfig::fromPrompts();

            $this->databaseService->configure($config);
            $this->call('key:generate');

            info('Environment configuration completed successfully!');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            error($e->getMessage());

            return self::FAILURE;
        }
    }
}
