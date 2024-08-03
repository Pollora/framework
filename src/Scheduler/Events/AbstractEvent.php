<?php

declare(strict_types=1);

namespace Pollen\Scheduler\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Pollen\Scheduler\Contracts\EventInterface;
use Pollen\Scheduler\Jobs\JobDispatcher;

abstract class AbstractEvent implements EventInterface, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    protected string $hook;

    protected int $timestamp;

    protected array $args;

    protected ?string $schedule = null;

    protected ?int $interval = null;

    public function __construct(?object $event = null)
    {
        if ($event) {
            $this->hook = $event->hook;
            $this->timestamp = $event->timestamp;
            $this->args = $event->args;
        }
    }

    public function getHook(): string
    {
        return $this->hook;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public static function createJob(object $event): self
    {
        $job = new static($event);
        $dispatcher = app(JobDispatcher::class);
        $jobId = $dispatcher->dispatch($job);
        $job->saveToDatabase($jobId);

        return $job;
    }

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

    abstract public function handle(): void;

    protected function deleteEvent()
    {
        DB::table('wp_events')->where('job_id', $this->job->getJobId())->delete();
    }
}
