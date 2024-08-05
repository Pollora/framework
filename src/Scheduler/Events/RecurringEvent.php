<?php

declare(strict_types=1);

namespace Pollen\Scheduler\Events;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/**
 * Class RecurringEvent
 * @package Pollen\Scheduler\Events
 */
class RecurringEvent extends AbstractEvent
{
    /**
     * RecurringEvent constructor.
     * @param object|null $event
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
     * @param object $event
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
     * @param int|null $jobId
     * @return void
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
     *
     * @param Schedule $schedule
     * @return void
     */
    public static function scheduleAllEvents(Schedule $schedule): void
    {
        $events = DB::table('wp_events')->whereNotNull('schedule')->get();

        foreach ($events as $event) {
            $schedule->call(function () use ($event) {
                do_action_ref_array($event->hook, json_decode($event->args, true));
            })->cron(self::getCronExpression($event->schedule, $event->interval));
        }
    }

    /**
     * Get the cron expression for a schedule.
     *
     * @param string $schedule
     * @param int|null $interval
     * @return string
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
                    } elseif ($minutes == 60) {
                        return '0 * * * *';
                    } elseif ($minutes % 60 == 0) {
                        $hours = $minutes / 60;

                        return "0 */{$hours} * * *";
                    } else {
                        return "*/{$minutes} * * * *";
                    }
                }

                return '0 0 * * *';
        }
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(): void
    {
        do_action_ref_array($this->hook, $this->args);
    }
}
