<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\UI\Console;

use Illuminate\Console\Command;
use Pollora\ObjectCache\Infrastructure\Config\Settings;
use Pollora\ObjectCache\Infrastructure\Management\Manager;

/**
 * Empties the object cache of this site, and only this site.
 */
final class FlushCommand extends Command
{
    protected $signature = 'pollora:object-cache:flush';

    protected $description = 'Empty the persistent object cache of this site';

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
