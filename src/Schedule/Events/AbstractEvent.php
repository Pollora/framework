<?php

declare(strict_types=1);

namespace Pollora\Schedule\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Pollora\Schedule\Contracts\EventInterface;
use Pollora\Schedule\Jobs\JobDispatcher;

/**
 * Abstract base class for WordPress scheduled events.
 *
 * Provides common functionality for handling WordPress cron events with
 * Laravel queue integration and database persistence.
 *
 * @implements EventInterface
 * @implements ShouldQueue
 */
abstract class AbstractEvent implements EventInterface, ShouldQueue
{
    public $job;

    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * The WordPress hook to execute.
     */
    protected string $hook;

    /**
     * The timestamp when the event should run.
     */
    protected int $timestamp;

    /**
     * The arguments to pass to the hook.
     */
    protected array $args;

    /**
     * The schedule frequency (e.g., 'hourly', 'daily').
     */
    protected ?string $schedule = null;

    /**
     * The interval between executions in seconds.
     */
    protected ?int $interval = null;

    /**
     * Create a new event instance.
     *
     * @param  object|null  $event  WordPress event object
     */
    public function __construct(?object $event = null)
    {
        if ($event) {
            $this->hook = $event->hook;
            $this->timestamp = $event->timestamp;
            $this->args = $event->args;
        }
    }

    /**
     * Get the event hook name.
     */
    public function getHook(): string
    {
        return $this->hook;
    }

    /**
     * Get the event timestamp.
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Get the event arguments.
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Create and persist a new job instance.
     *
     * @param  object  $event  WordPress event object
     */
    public static function createJob(object $event): self
    {
        $job = new static($event);
        $dispatcher = app(JobDispatcher::class);
        $jobId = $dispatcher->dispatch($job);
        $job->saveToDatabase($jobId);

        return $job;
    }

    /**
     * Save the event to the database.
     *
     * @param  int  $jobId  The queue job ID
     */
    protected function saveToDatabase($jobId)
    {
        DB::table('wp_events')->insert([
            'hook' => $this->hook,
            'args' => json_encode($this->args),
            'schedule' => $this->schedule,
            'interval' => $this->interval,
            'is_recurring' => ! is_null($this->schedule),
            'timestamp' => $this->timestamp,
            'job_id' => $jobId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete the event from the database.
     */
    protected function deleteEvent()
    {
        DB::table('wp_events')->where('job_id', $this->job->getJobId())->delete();
    }

    /**
     * Handle the event execution.
     */
    abstract public function handle(): void;
}
