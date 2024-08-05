<?php

declare(strict_types=1);

namespace Pollen\Scheduler;

use Illuminate\Support\Facades\DB;
use Pollen\Scheduler\Contracts\SchedulerInterface;
use Pollen\Scheduler\Events\AbstractEvent;
use Pollen\Scheduler\Events\RecurringEvent;
use Pollen\Scheduler\Events\SingleEvent;
use WP_Error;

class Scheduler implements SchedulerInterface
{
    /**
     * Handle the cron option update.
     *
     * @param  array  $value  The new cron option value.
     * @param  array  $old_value  The old cron option value.
     * @return array The old value to prevent WordPress from updating the option.
     */
    public function preUpdateOptionCron(array $value, array $old_value): array
    {
        $this->processCronDifferences($old_value, $value);

        return $old_value;
    }

    /**
     * Retrieve the cron option.
     *
     * @param  mixed  $value  The current option value.
     * @return array The cron jobs array.
     */
    public function preOptionCron($value): array
    {
        if ($value !== false) {
            return $value;
        }

        return $this->generateCronArray();
    }

    /**
     * Schedule a new event.
     *
     * @param  mixed  $pre  The pre-filtered value.
     * @param  object  $event  The event to schedule.
     * @param  bool  $wp_error  Whether to return a WP_Error on failure.
     * @return AbstractEvent|WP_Error|null The scheduled job or WP_Error.
     */
    public function preScheduleEvent($pre, object $event, bool $wp_error): AbstractEvent|WP_Error|null
    {
        if ($pre !== null) {
            return $pre;
        }

        try {
            if ($event->schedule) {
                $job = new RecurringEvent($event);
            } else {
                $job = new SingleEvent($event);
            }

            return $job->createJob($event);
        } catch (\Throwable $e) {
            return $wp_error ? new WP_Error('schedule_error', $e->getMessage()) : null;
        }
    }

    /**
     * Reschedule an event.
     *
     * @param  mixed  $pre  The pre-filtered value.
     * @param  object  $event  The event to reschedule.
     * @param  bool  $wp_error  Whether to return a WP_Error on failure.
     * @return AbstractEvent|WP_Error|null The rescheduled job or WP_Error.
     */
    public function preRescheduleEvent($pre, object $event, bool $wp_error): AbstractEvent|WP_Error|null
    {
        if ($pre !== null) {
            return $pre;
        }

        try {
            $job = new RecurringEvent($event);

            return $job->createJob($event);
        } catch (\Throwable $e) {
            return $wp_error ? new WP_Error('reschedule_error', $e->getMessage()) : null;
        }
    }

    /**
     * Unschedule an event.
     *
     * @param  mixed  $pre  The pre-filtered value.
     * @param  int  $timestamp  The event timestamp.
     * @param  string  $hook  The event hook.
     * @param  array  $args  The event arguments.
     * @param  bool  $wp_error  Whether to return a WP_Error on failure.
     * @return bool|WP_Error Whether the event was unscheduled.
     */
    public function preUnscheduleEvent($pre, int $timestamp, string $hook, array $args, bool $wp_error): bool|WP_Error
    {
        if ($pre !== null) {
            return $pre;
        }

        try {
            $deleted = DB::table('wp_events')
                ->where('hook', $hook)
                ->where('timestamp', $timestamp)
                ->where('args', json_encode($args))
                ->delete();

            if ($deleted) {
                $this->deleteAssociatedJob($hook, $timestamp, $args);
            }

            return $deleted > 0;
        } catch (\Throwable $e) {
            return $wp_error ? new WP_Error('unschedule_error', $e->getMessage()) : false;
        }
    }

    /**
     * Clear all scheduled hooks.
     *
     * @param  mixed  $pre  The pre-filtered value.
     * @param  string  $hook  The hook to clear.
     * @param  array|null  $args  The arguments to match.
     * @param  bool  $wp_error  Whether to return a WP_Error on failure.
     * @return int|WP_Error The number of events cleared.
     */
    public function preClearScheduledHook($pre, string $hook, ?array $args, bool $wp_error): int|WP_Error
    {
        if ($pre !== null) {
            return $pre;
        }

        try {
            $query = DB::table('wp_events')->where('hook', $hook);

            if ($args !== null) {
                $query->where('args', json_encode($args));
            }

            $events = $query->get();
            $count = $events->count();

            foreach ($events as $event) {
                $this->deleteAssociatedJob($event->hook, $event->timestamp, json_decode($event->args, true));
            }

            $query->delete();

            return $count;
        } catch (\Throwable $e) {
            return $wp_error ? new WP_Error('clear_hook_error', $e->getMessage()) : 0;
        }
    }

    protected function deleteAssociatedJob(string $hook, int $timestamp, array $args)
    {
        $jobId = DB::table('wp_events')
            ->where('hook', $hook)
            ->where('timestamp', $timestamp)
            ->where('args', json_encode($args))
            ->value('job_id');

        if ($jobId) {
            DB::table('jobs')->where('id', $jobId)->delete();
        }
    }

    /**
     * Unschedule all events attached to a specific hook.
     *
     * @param  mixed  $pre  The pre-filtered value.
     * @param  string  $hook  The hook to unschedule.
     * @param  bool  $wp_error  Whether to return a WP_Error on failure.
     * @return int|WP_Error The number of events unscheduled.
     */
    public function preUnscheduleHook($pre, string $hook, bool $wp_error): int|WP_Error
    {
        return $this->preClearScheduledHook($pre, $hook, null, $wp_error);
    }

    /**
     * Retrieve a scheduled event.
     *
     * @param  mixed  $pre  The pre-filtered value.
     * @param  string  $hook  The event hook.
     * @param  array  $args  The event arguments.
     * @param  int|null  $timestamp  Unix timestamp (UTC) of the event. Null to retrieve next scheduled event.
     * @return object|false The event object or false if not found.
     */
    public function preGetScheduledEvent($pre, string $hook, array $args, ?int $timestamp): object|false
    {
        if ($pre !== null) {
            return $pre;
        }

        $query = DB::table('wp_events')
            ->where('hook', $hook);

        if (! empty($args)) {
            $query->where('args', json_encode($args));
        }

        if ($timestamp !== null) {
            $query->where('timestamp', $timestamp);
        }

        $event = $query->first();

        if (! $event) {
            return false;
        }

        return $this->convertDbEventToWpEvent($event);
    }

    protected function convertDbEventToWpEvent(object $event): object
    {
        return (object) [
            'hook' => $event->hook,
            'timestamp' => $event->schedule ? $this->getNextRunTime($event->schedule, $event->interval) : $event->timestamp,
            'schedule' => $event->schedule,
            'args' => json_decode($event->args, true),
            'interval' => $event->interval,
        ];
    }

    protected function getNextRunTime(string $schedule, ?int $interval): int
    {
        $cron = RecurringEvent::getCronExpression($schedule, $interval);
        $cron = new \Cron\CronExpression($cron);

        return $cron->getNextRunDate()->getTimestamp();
    }

    /**
     * Generate a unique job ID.
     *
     * @param  int|null  $timestamp  The event timestamp.
     * @param  string  $hook  The event hook.
     * @param  array  $args  The event arguments.
     * @return string The generated job ID.
     */
    protected function generateJobId(?int $timestamp, string $hook, array $args): string
    {
        return md5(serialize([$timestamp, $hook, $args]));
    }

    /**
     * Convert a job to a WordPress event object.
     *
     * @param  object  $job  The job to convert.
     * @return object The converted event object.
     */
    protected function convertJobToEvent(object $job): object
    {
        $event = new \stdClass();
        $event->hook = $job->wp_hook;
        $event->timestamp = $job->available_at;
        $event->args = json_decode($job->wp_args, true);
        $event->schedule = $job->wp_schedule ?? false;
        $event->interval = $job->wp_interval ?? null;

        return $event;
    }

    /**
     * Process the differences between old and new cron arrays.
     *
     * @param  array  $oldCrons  The old cron array.
     * @param  array  $newCrons  The new cron array.
     */
    protected function processCronDifferences(array $oldCrons, array $newCrons): void
    {
        // Implementation to handle differences (add, update, delete jobs)
    }

    /**
     * Generate a cron array from the current queue jobs.
     *
     * @return array The generated cron array.
     */
    protected function generateCronArray(): array
    {
        $jobs = $this->getAllJobs();

        return $this->convertJobsToWordPressCronArray($jobs);
    }

    /**
     * Get all jobs from the queue.
     */
    protected function getAllJobs(): array
    {
        return DB::table('wp_events')
            ->orderBy('timestamp', 'asc')
            ->get()
            ->map(function ($job) {
                return $this->convertDbEventToWpEvent($job);
            })
            ->all();
    }

    /**
     * Retrieve cron jobs ready to be run.
     *
     * @param  mixed  $pre  The pre-filtered value.
     * @return array The array of ready cron jobs.
     */
    public function preGetReadyCronJobs($pre): array
    {
        if ($pre !== null) {
            return $pre;
        }

        return [];
    }

    /**
     * Convert an array of jobs to a WordPress cron array format.
     *
     * @param  array  $jobs  The jobs to convert.
     * @return array The converted cron array.
     */
    protected function convertJobsToWordPressCronArray(array $jobs): array
    {
        $crons = [];

        foreach ($jobs as $job) {
            if (! $job->hook) {
                continue; // Skip jobs that are not WordPress cron jobs
            }

            $timestamp = $job->timestamp;
            $hook = $job->hook;
            $key = md5(serialize($job->args));

            if (! isset($crons[$timestamp])) {
                $crons[$timestamp] = [];
            }

            if (! isset($crons[$timestamp][$hook])) {
                $crons[$timestamp][$hook] = [];
            }

            $crons[$timestamp][$hook][$key] = [
                'schedule' => $job->schedule,
                'args' => $job->args,
            ];

            if ($job->interval) {
                $crons[$timestamp][$hook][$key]['interval'] = $job->interval;
            }
        }

        ksort($crons, SORT_NUMERIC);

        $crons['version'] = 2;

        return $crons;
    }
}
