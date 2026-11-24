<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\UI\Console;

use Illuminate\Console\Command;
use Pollora\ObjectCache\Infrastructure\Config\Settings;
use Pollora\ObjectCache\Infrastructure\Management\Manager;

/**
 * Says whether the object cache is installed and working, and with which settings.
 */
final class StatusCommand extends Command
{
    protected $signature = 'pollora:object-cache:status';

    protected $description = 'Show whether the persistent object cache is installed and reachable';

    public function handle(Manager $manager, Settings $settings): int
    {
        $report = $manager->status($settings);

        foreach ($report->lines as $label => $value) {
            $this->components->twoColumnDetail($label, $value);
        }

        return $report->healthy ? self::SUCCESS : self::FAILURE;
    }
}
