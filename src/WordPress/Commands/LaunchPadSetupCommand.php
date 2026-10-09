<?php

declare(strict_types=1);

namespace Pollora\WordPress\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[Description('Configure environment for WordPress installation')]
#[Signature('pollora:env:setup {--install : Suppress some informational output}', aliases: ['pollora:env-setup'])]
class LaunchPadSetupCommand extends Command
{
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

            // This command runs from composer's post-autoload-dump hook, and
            // that hook fires where there is frequently no terminal to answer
            // a question: a container build, a deployment, CI. Laravel Prompts
            // throws there, the throw was caught below, and `composer install`
            // exited 1 with a message about prompting rather than about the
            // database — which is a confusing way to learn that the database
            // was simply not up yet.
            //
            // Nothing here is a gate. `pollora:install` is what refuses to
            // continue without a database, and it says so plainly. So when
            // there is nobody to ask, say what is wrong and what to run, and
            // let composer finish.
            //
            // Composer runs the hook without a terminal even when the user
            // typed `composer create-project` in one: the input still says it
            // is interactive, but Laravel Prompts reads STDIN, answers each
            // question with its default and fails on the first required one
            // ("Database name is required", Pollora/pollora#78).
            if (! $this->input->isInteractive() || ! $this->hasTerminal()) {
                warning('The database is not reachable, and there is no terminal to ask for its settings.');
                $this->line('  Set DB_CONNECTION=mysql and the DB_* values in .env, or run: php artisan pollora:env:setup');
                $this->line('  Then: php artisan pollora:install (php artisan pollora:doctor checks the project)');

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

        } catch (\Throwable $throwable) {
            error($throwable->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Whether someone can answer a question on STDIN.
     */
    protected function hasTerminal(): bool
    {
        return defined('STDIN') && stream_isatty(STDIN);
    }
}
