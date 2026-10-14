<?php

declare(strict_types=1);

namespace Pollora\Schedule\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Schedule\Infrastructure\Services\LateEventRecovery;

/**
 * Sends again the scheduled events whose job was lost, for the "laravel" driver.
 */
#[Description('Send again the scheduled events a queue lost (laravel driver)')]
#[Signature('pollora:schedule:recover
        {--network : Every site of the network}
        {--dry-run : List the late events without sending them}')]
final class ScheduleRecoverCommand extends Command
{
    public function handle(LateEventRecovery $recovery): int
    {
        $sites = $this->option('network') && function_exists('is_multisite') && is_multisite()
            ? array_map(static fn ($site): int => (int) $site->blog_id, get_sites(['fields' => 'objects', 'number' => 0]))
            : [null];

        $total = 0;

        foreach ($sites as $site) {
            if ($site !== null) {
                switch_to_blog($site);
            }

            try {
                $total += $this->option('dry-run') ? $this->list($recovery, $site) : $recovery->recover(force: true);
            } finally {
                if ($site !== null) {
                    restore_current_blog();
                }
            }
        }

        $this->info(sprintf($this->option('dry-run') ? '%d late event(s).' : '%d event(s) sent again.', $total));

        return self::SUCCESS;
    }

    private function list(LateEventRecovery $recovery, ?int $site): int
    {
        $late = $recovery->late();

        foreach ($late as $event) {
            $this->line(sprintf('%s%s  %s', $site === null ? '' : sprintf('[site %d] ', $site), gmdate('Y-m-d H:i:s', $event->timestamp), $event->hook));
        }

        return count($late);
    }
}
