<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\ObjectCache\Infrastructure\Config\Settings;
use Pollora\ObjectCache\Infrastructure\Management\Manager;

/**
 * Empties the object cache of this site, and only this site.
 */
#[Description('Empty the persistent object cache of this site')]
#[Signature('pollora:object-cache:flush')]
final class FlushCommand extends Command
{
    public function handle(Manager $manager, Settings $settings): int
    {
        if (! $manager->flush($settings)) {
            $this->components->error('Redis could not be reached, nothing was flushed.');

            return self::FAILURE;
        }

        $this->components->info('The object cache of this site is empty.');

        return self::SUCCESS;
    }
}
