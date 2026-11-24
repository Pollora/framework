<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\ObjectCache\Infrastructure\Exceptions\DropInFileException;
use Pollora\ObjectCache\Infrastructure\Exceptions\ForeignDropInException;
use Pollora\ObjectCache\Infrastructure\Management\Manager;
use Pollora\ObjectCache\Infrastructure\WordPress\DropInStatus;

/**
 * Removes the `object-cache.php` drop-in, if it is the package's.
 */
#[Description('Remove the object-cache.php drop-in: WordPress goes back to its in-memory cache')]
#[Signature('pollora:object-cache:disable')]
final class DisableCommand extends Command
{
    public function handle(Manager $manager): int
    {
        try {
            $before = $manager->disable();
        } catch (ForeignDropInException|DropInFileException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($before === DropInStatus::Missing) {
            $this->components->info('Not installed, nothing to do.');

            return self::SUCCESS;
        }

        $this->components->info('Removed the drop-in. What Redis holds stays there: run pollora:object-cache:flush first to empty it.');

        return self::SUCCESS;
    }
}
