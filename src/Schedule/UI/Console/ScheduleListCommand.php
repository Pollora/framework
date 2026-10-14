<?php

declare(strict_types=1);

namespace Pollora\Schedule\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Schedule\Application\Services\ScheduleStatus;
use Pollora\Schedule\Event;
use Pollora\Schedule\Schedule;

/**
 * Lists the scheduled events of the site, with the driver that keeps each.
 *
 * It reads the 'cron' option, which indexes every driver's events: no query
 * reaches Action Scheduler or a queue.
 */
#[Description('List the scheduled events, with their type and the driver that keeps them')]
#[Signature('pollora:schedule:list
                            {--type= : Only "once" or "recurring" events}
                            {--driver= : Only events kept by this driver (wp-cron, action-scheduler…)}
                            {--site= : The site of a multisite network, by ID}
                            {--network : Every site of a multisite network}
                            {--json : Output JSON, for scripts}')]
class ScheduleListCommand extends Command
{
    private const array BUILT_IN = ['hourly', 'twicedaily', 'daily', 'weekly'];

    public function __construct(private readonly ScheduleStatus $status)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type');

        if ($type !== null && ! in_array($type, ['once', 'recurring'], true)) {
            $this->error('--type is "once" or "recurring".');

            return self::FAILURE;
        }

        if (! function_exists('_get_cron_array')) {
            $this->error('WordPress is not loaded.');

            return self::FAILURE;
        }

        $rows = $this->rows(is_string($type) ? $type : null, is_string($this->option('driver')) ? $this->option('driver') : null);

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->info('No scheduled event matches.');

            return self::SUCCESS;
        }

        $network = (bool) $this->option('network');

        $this->table(
            [...($network ? ['Site'] : []), 'Hook', 'Type', 'Frequency', 'Driver', 'Next run', 'Arguments'],
            array_map(static fn (array $row): array => [
                ...($network ? [$row['site']] : []),
                $row['hook'],
                $row['type'],
                $row['frequency'],
                $row['driver_label'],
                $row['next_run'],
                $row['args'] === [] ? '—' : (string) json_encode($row['args'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ], $rows),
        );

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(?string $type, ?string $driver): array
    {
        $sites = $this->sites();
        $rows = [];

        foreach ($sites as $site) {
            if ($site !== null) {
                switch_to_blog($site);
            }

            try {
                foreach (Schedule::engine()->index() as $event) {
                    if (($type !== null && ($event->isRecurring() ? 'recurring' : 'once') !== $type) || ($driver !== null && $event->driver !== $driver)) {
                        continue;
                    }

                    $rows[] = $this->row($event, $site);
                }
            } finally {
                if ($site !== null) {
                    restore_current_blog();
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

        return $rows;
    }

    /**
     * The sites to read: the current one (null), one by ID, or the whole network.
     *
     * @return list<int|null>
     */
    private function sites(): array
    {
        if ($this->option('network') && function_exists('is_multisite') && is_multisite()) {
            return array_map(intval(...), (array) get_sites(['fields' => 'ids', 'number' => 0]));
        }

        $site = $this->option('site');

        return is_numeric($site) && function_exists('is_multisite') && is_multisite() ? [(int) $site] : [null];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Event $event, ?int $site): array
    {
        return [
            'site' => $site ?? (function_exists('get_current_blog_id') ? get_current_blog_id() : 1),
            'hook' => $event->hook,
            'type' => $event->isRecurring() ? 'recurring' : 'once',
            'recurrence' => $event->recurrence,
            'frequency' => $this->frequency($event),
            'interval' => $event->interval,
            'driver' => $event->driver,
            'driver_label' => $this->status->label($event),
            'timestamp' => $event->timestamp,
            'next_run' => function_exists('wp_date') ? (string) wp_date('Y-m-d H:i', $event->timestamp) : gmdate('Y-m-d H:i', $event->timestamp),
            'args' => $event->args,
        ];
    }

    private function frequency(Event $event): string
    {
        if ($event->recurrence === null) {
            return '—';
        }

        if (in_array($event->recurrence, self::BUILT_IN, true) || $event->interval === null) {
            return $event->recurrence;
        }

        foreach ([86400 => ' day', 3600 => ' hour', 60 => ' min'] as $seconds => $unit) {
            if ($event->interval % $seconds === 0) {
                $count = intdiv($event->interval, $seconds);

                return $count.$unit.($count > 1 && $seconds !== 60 ? 's' : '');
            }
        }

        return $event->interval.' s';
    }
}
