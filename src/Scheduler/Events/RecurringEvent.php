<?php

declare(strict_types=1);

namespace Pollora\Scheduler\Events;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/**
 * Class for handling recurring WordPress cron events.
 *
 * Manages events that need to run on a schedule (hourly, daily, etc.)
 * with database persistence and Laravel queue integration.
 *
 * @extends AbstractEvent
 */
class RecurringEvent extends AbstractEvent
{
    /**
     * Create a new recurring event instance.
     *
     * @param  object|null  $event  WordPress event object
     */
    public function __construct(?object $event = null)
    {
        parent::__construct($event);
        if ($event) {
            $this->schedule = $event->schedule;
            $this->interval = $event->interval;
        }
    }

    /**
     * Create and persist a new recurring job instance.
     *
     * @param  object  $event  WordPress event object
     * @return static
     */
    public static function createJob(object $event): self
    {
        $job = new static($event);
        $job->saveToDatabase();

        return $job;
    }

    /**
     * Save the recurring job to the database.
     *
     * @param  int|null  $jobId  Optional job ID
     */
    protected function saveToDatabase($jobId = null): void
    {
        DB::table('wp_events')->insert([
            'hook' => $this->hook,
            'args' => json_encode($this->args),
            'schedule' => $this->schedule,
            'interval' => $this->interval,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Schedule all recurring events in the Laravel scheduler.
     *
     * @param  Schedule  $schedule  Laravel schedule instance
     */
    public static function scheduleAllEvents(Schedule $schedule): void
    {
        $events = DB::table('wp_events')->whereNotNull('schedule')->get();

        foreach ($events as $event) {
            $schedule->call(function () use ($event): void {
                do_action_ref_array($event->hook, json_decode($event->args, true));
            })->cron(self::getCronExpression($event->schedule, $event->interval));
        }
    }

    /**
     * Convert WordPress schedule to cron expression.
     *
     * @param  string  $schedule  WordPress schedule name
     * @param  int|null  $interval  Custom interval in seconds
     * @return string Cron expression
     */
    public static function getCronExpression(string $schedule, ?int $interval): string
    {
        $schedules = wp_get_schedules();

        if (isset($schedules[$schedule])) {
            $interval = $schedules[$schedule]['interval'];
        }

        return match ($schedule) {
            'minutly' => '* * * * *',
            'hourly' => '0 * * * *',
            'twicedaily' => '0 */12 * * *',
            'daily' => '0 0 * * *',
            'weekly' => '0 0 * * 0',
            default => self::calculateCustomInterval($interval),
        };
    }

    /**
     * Handle the recurring event execution.
     */
    public function handle(): void
    {
        do_action_ref_array($this->hook, $this->args);
    }

    /**
     * Calculate cron expression for custom interval.
     *
     * @param  int|null  $interval  Interval in seconds
     * @return string Cron expression
     */
    private static function calculateCustomInterval(?int $interval): string
    {
        if (! $interval) {
            return '0 0 * * *';
        }

        $minutes = $interval / 60;

        if ($minutes < 60) {
            return "*/{$minutes} * * * *";
        }

        if ($minutes == 60) {
            return '0 * * * *';
        }

        if ($minutes % 60 == 0) {
            $hours = $minutes / 60;

            return "0 */{$hours} * * *";
        }

        return "*/{$minutes} * * * *";
    }
}
