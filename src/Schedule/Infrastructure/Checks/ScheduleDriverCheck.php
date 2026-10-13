<?php

declare(strict_types=1);

namespace Pollora\Schedule\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Schedule\Application\Services\ScheduleStatus;
use Pollora\Schedule\Infrastructure\Drivers\LaravelQueueDriver;
use Pollora\Schedule\Infrastructure\Services\ScheduleFailures;

/**
 * Scheduled events are where the configuration says they should be.
 *
 * Neither failure shows by itself: an unknown or missing driver leaves every
 * event on WP-Cron, and events a former driver still keeps are run by that
 * driver, or by nobody once it is gone.
 */
final readonly class ScheduleDriverCheck implements CheckInterface
{
    public function __construct(private ScheduleStatus $status) {}

    public function id(): string
    {
        return 'schedule-driver';
    }

    public function label(): string
    {
        return 'Scheduled events';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('_get_cron_array') || ! function_exists('did_action') || \did_action('init') === 0) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        /** @var list<string> $problems */
        $problems = [];
        $fix = null;

        if ($this->status->unknown()) {
            $problems[] = sprintf('config/schedule.php asks for the driver "%s", which does not exist: every event goes to WP-Cron', $this->status->configured());
            $fix = 'Set POLLORA_SCHEDULE_DRIVER (config/schedule.php driver) to wp-cron, action-scheduler or laravel';
        } elseif ($this->status->unavailable()) {
            $problems[] = sprintf('The "%s" driver is not available, so events stay on WP-Cron', $this->status->active());
            $fix = $this->status->active() === LaravelQueueDriver::NAME
                ? 'Point the queue at a connection a worker runs (database, redis, sqs…), never sync or null: QUEUE_CONNECTION or POLLORA_SCHEDULE_QUEUE_CONNECTION'
                : 'Activate a plugin that bundles Action Scheduler (WooCommerce does), or set the driver back to wp-cron';
        }

        foreach ($this->status->toMigrate() as $driver => $count) {
            $problems[] = sprintf('%d event(s) are still kept by "%s", not by the driver of the site ("%s")', $count, $driver, $this->status->active());
            $fix ??= sprintf('php artisan pollora:schedule:migrate --from=%s --to=%s (--dry-run shows what would move)', $driver, $this->status->active());
        }

        $late = $this->status->late();

        if ($late > 0) {
            $problems[] = sprintf('%d scheduled event(s) are more than %d minutes late: no queue worker is running them', $late, intdiv($this->status->lateAfter(), 60));
            $fix ??= 'Run a queue worker (php artisan queue:work), or send lost jobs again with php artisan pollora:schedule:recover';
        }

        foreach (array_slice(ScheduleFailures::recent(), -3) as $failure) {
            $problems[] = sprintf('"%s" failed for good on %s: %s', $failure['hook'], gmdate('Y-m-d H:i', $failure['time']).' UTC', $failure['message']);
            $fix ??= 'See php artisan queue:failed; the event is out of the schedule if it was a single one';
        }

        if ($problems === []) {
            return CheckResult::ok(sprintf('Scheduled events are kept by "%s".', $this->status->active()));
        }

        return CheckResult::warning(sprintf('%d problem(s) with scheduled events.', count($problems)), $problems, (string) $fix);
    }
}
