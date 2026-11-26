<?php

declare(strict_types=1);

namespace Pollora\WordPress\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Services\WordPress\Installation\SaltsService;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[Description('Generate the WordPress authentication keys and salts in .env')]
#[Signature('pollora:salts:generate {--force : Replace the ones that are already there: every session ends}')]
class SaltsGenerateCommand extends Command
{
    public function __construct(private readonly SaltsService $salts)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->salts->canWrite()) {
            error('There is no .env file here: set the keys and salts where your environment variables are kept.');
            $this->line('  '.implode(', ', SaltsService::KEYS));

            return self::FAILURE;
        }

        $keys = $this->option('force') ? SaltsService::KEYS : $this->salts->missing();

        if ($keys === []) {
            info('The keys and salts are already set; --force replaces them (everyone has to log in again).');

            return self::SUCCESS;
        }

        $this->salts->generate($keys);

        // The values are never printed
        info(sprintf('%d key(s) and salt(s) written to .env.', count($keys)));

        if ($this->option('force')) {
            warning('Every session ends: everyone has to log in again.');
        }

        return self::SUCCESS;
    }
}
