<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\ObjectCache\Infrastructure\Config\Settings;
use Pollora\ObjectCache\Infrastructure\Exceptions\DropInFileException;
use Pollora\ObjectCache\Infrastructure\Exceptions\ForeignDropInException;
use Pollora\ObjectCache\Infrastructure\Management\Manager;
use Pollora\ObjectCache\Infrastructure\WordPress\DropInStatus;

/**
 * Installs the `object-cache.php` drop-in, or brings it up to date.
 */
#[Description('Install the object-cache.php drop-in that turns the persistent object cache on')]
#[Signature('pollora:object-cache:enable {--force : Replace an object-cache.php that belongs to another object cache}')]
final class EnableCommand extends Command
{
    public function handle(Manager $manager, Settings $settings): int
    {
        try {
            $before = $manager->enable((bool) $this->option('force'));
        } catch (ForeignDropInException|DropInFileException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        match ($before) {
            DropInStatus::Missing => $this->components->info('Installed the drop-in.'),
            DropInStatus::Outdated => $this->components->info('Updated the drop-in.'),
            DropInStatus::Current => $this->components->info('Already installed, nothing to do.'),
            DropInStatus::Foreign => $this->components->warn('Replaced another object cache.'),
        };

        $report = $manager->status($settings);

        if (! $report->healthy) {
            $this->components->warn('The cache is not serving yet ('.($report->lines['Redis'] ?? '').'): the site runs on its in-memory cache until it does.');
        }

        return self::SUCCESS;
    }
}
