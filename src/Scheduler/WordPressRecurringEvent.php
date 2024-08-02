<?php

declare(strict_types=1);

namespace Pollen\Scheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class WordPressRecurringEvent extends WordPressEvent
{
    protected ?string $schedule;
    protected ?int $interval;

    public function __construct(?object $event = null)
    {
        parent::__construct($event);
        if ($event) {
            $this->schedule = $event->schedule;
            $this->interval = $event->interval;
        }
    }

    public static function createJob(object $event)
    {
        $job = new static($event);
        $job->saveToDatabase();
        return $job;
    }

    protected function saveToDatabase($jobId = null)
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

    public static function scheduleAllEvents(Schedule $schedule)
    {
        $events = DB::table('wp_events')->whereNotNull('schedule')->get();

        foreach ($events as $event) {
            $schedule->call(function () use ($event) {
                do_action_ref_array($event->hook, json_decode($event->args, true));
            })->cron(self::getCronExpression($event->schedule, $event->interval));
        }
    }

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
                        return "0 * * * *";
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

    public function handle()
    {
    }
}
