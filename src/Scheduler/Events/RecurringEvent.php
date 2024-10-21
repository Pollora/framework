<?php

declare(strict_types=1);

namespace Pollora\Scheduler\Events;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/**
 * Class RecurringEvent
 */
class RecurringEvent extends AbstractEvent
{
    /**
     * RecurringEvent constructor.
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
     * Create a new job instance.
     *
     * @return static
     */
    public static function createJob(object $event): self
    {
        $job = new static($event);
        $job->saveToDatabase();

        return $job;
    }

    /**
     * Save the job to the database.
     *
     * @param  int|null  $jobId
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
     * Schedule all events.
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
     * Get the cron expression for a schedule.
     */
    public static function getCronExpression(string $schedule, ?int $interval): string
    {
        $schedules = wp_get_schedules();

        if (isset($schedules[$schedule])) {
            $interval = $schedules[$schedule]['interval'];
        }

        switch ($schedule) {
            case 'minutly':
                return '* * * * *';
            case 'hourly':
                return '0 * * * *';
            case 'twicedaily':
                return '0 */12 * * *';
            case 'daily':
                return '0 0 * * *';
            case 'weekly':
                return '0 0 * * 0';
            default:
                if ($interval) {
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

                return '0 0 * * *';
        }
    }

    /**
     * Handle the event.
     */
    public function handle(): void
    {
        do_action_ref_array($this->hook, $this->args);
    }
}
