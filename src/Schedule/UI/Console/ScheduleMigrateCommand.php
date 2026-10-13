<?php

declare(strict_types=1);

namespace Pollora\Schedule\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Schedule\Application\Services\ScheduleStatus;
use Pollora\Schedule\Migration\Migrator;
use Pollora\Schedule\Schedule;
use Pollora\Schedule\Support\CronIndex;

/**
 * Moves the pending events of a driver to another: explicit, reversible, and safe to run again.
 */
#[Description('Move the pending scheduled events from one driver to another')]
#[Signature('pollora:schedule:migrate
                            {--from= : The driver that keeps the events now (wp-cron, or the one still holding events)}
                            {--to= : The driver that takes them (the driver of the site)}
                            {--dry-run : Show what would move, and move nothing}')]
class ScheduleMigrateCommand extends Command
{
    public function __construct(private readonly ScheduleStatus $status)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! function_exists('_get_cron_array')) {
            $this->error('WordPress is not loaded.');

            return self::FAILURE;
        }

        $to = is_string($this->option('to')) && $this->option('to') !== '' ? $this->option('to') : $this->status->active();
        $from = is_string($this->option('from')) && $this->option('from') !== '' ? $this->option('from') : $this->guessSource($to);

        if ($from === null) {
            $this->error('No other driver keeps events: say which one with --from.');

            return self::FAILURE;
        }

        try {
            $report = (new Migrator(Schedule::engine()))->migrate($from, $to, (bool) $this->option('dry-run'));
        } catch (\Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $verb = $report->dryRun ? 'Would move' : 'Moved';

        foreach ($report->moved as $event) {
            $this->line(sprintf('%s %s (%s)', $verb, $event->hook, $event->recurrence ?? 'once'));
        }

        foreach ($report->skipped as $skipped) {
            $this->line(sprintf('Kept %s: %s', $skipped['event']->hook, $skipped['reason']));
        }

        foreach ($report->failed as $failed) {
            $this->warn(sprintf('Could not move %s: %s', $failed['event']->hook, $failed['reason']));
        }

        $summary = sprintf('%s %d event(s) from %s to %s%s.', $verb, count($report->moved), $from, $to, $report->dryRun ? ' (dry run)' : '');

        if (! $report->succeeded()) {
            $this->error($summary.' Some events stayed where they were.');

            return self::FAILURE;
        }

        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * WP-Cron holds what a driver has not taken; when the target is WP-Cron, the driver that still holds events.
     */
    private function guessSource(string $to): ?string
    {
        if ($to !== CronIndex::WP_CRON) {
            return CronIndex::WP_CRON;
        }

        foreach (Schedule::engine()->index() as $event) {
            if ($event->driver !== CronIndex::WP_CRON) {
                return (string) $event->driver;
            }
        }

        return null;
    }
}
