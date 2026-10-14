<?php

declare(strict_types=1);

namespace Pollora\Schedule\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Pollora\Schedule\Event;
use Pollora\Schedule\Infrastructure\Drivers\LaravelQueueDriver;
use Pollora\Schedule\Infrastructure\Services\ScheduleFailures;
use Pollora\Schedule\Support\CronIndex;
use Throwable;

/**
 * Fires the hook of an event the "laravel" driver keeps, in a queue worker.
 *
 * The job is a promise, not the event: it runs only if the 'cron' index still
 * has the event, with this job's identifier and time. A cancelled event, a
 * moved one, or a copy the recovery sent again after the first ran, all find
 * the entry gone or changed and stop. A recurring event queues its next
 * occurrence once this one ran, or failed for good.
 */
#[Backoff([30, 300])]
#[Tries(3)]
final class RunScheduledEvent implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * @param  array<int|string, mixed>  $args
     */
    public function __construct(
        public readonly string $hook,
        public readonly array $args,
        public readonly int $timestamp,
        public readonly string $id,
        public readonly ?string $recurrence = null,
        public readonly ?int $interval = null,
        public readonly int $site = 1,
    ) {}

    public static function for(Event $event): self
    {
        return new self(
            $event->hook,
            $event->args,
            $event->timestamp,
            (string) $event->id,
            $event->recurrence,
            $event->interval,
            function_exists('get_current_blog_id') ? get_current_blog_id() : 1,
        );
    }

    public function handle(LaravelQueueDriver $driver): void
    {
        $this->inSite(function () use ($driver): void {
            if (! $this->entryHolds()) {
                return;
            }

            // The delay was cut short for a connection with a ceiling: wait again
            if ($this->timestamp - time() > 1) {
                $driver->send($this->again());

                return;
            }

            // One run at a time: a copy sent again while this one is still working finds the lock taken
            $lock = Cache::lock('pollora_schedule_run_'.$this->id, 3600);

            if (! $lock->get()) {
                return;
            }

            try {
                do_action_ref_array($this->hook, $this->args);
                $this->conclude($driver);
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * Retries are over: a recurring event goes on with its next occurrence, a single one leaves the index, and the doctor is told.
     */
    public function failed(Throwable $exception): void
    {
        $this->inSite(function () use ($exception): void {
            if (! $this->entryHolds()) {
                return;
            }

            ScheduleFailures::record($this->hook, $exception->getMessage());
            $this->conclude(resolve(LaravelQueueDriver::class));
        });
    }

    /**
     * Name shown by Horizon and in the failed jobs table.
     */
    public function displayName(): string
    {
        return 'pollora/schedule '.$this->hook;
    }

    /**
     * Tags for Horizon.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return ['pollora/schedule', $this->hook];
    }

    /**
     * Whether the index still has this event, at this time, under this identifier.
     */
    private function entryHolds(): bool
    {
        // A worker lives across jobs: what WordPress cached in memory, the cron option included, is from before
        if (function_exists('wp_cache_flush_runtime')) {
            wp_cache_flush_runtime();
        }

        foreach (CronIndex::events(LaravelQueueDriver::NAME) as $event) {
            if ($event->hook === $this->hook && $event->args === $this->args) {
                return $event->id === $this->id && $event->timestamp === $this->timestamp;
            }
        }

        return false;
    }

    /**
     * What follows a run: the next occurrence of a recurring event (never in the past), or the end of a single one.
     */
    private function conclude(LaravelQueueDriver $driver): void
    {
        if ($this->recurrence === null || $this->interval === null || $this->interval <= 0) {
            CronIndex::forget($this->hook, $this->args, LaravelQueueDriver::NAME);

            return;
        }

        $next = $this->timestamp + $this->interval;

        if ($next <= time()) {
            $next += (int) ceil((time() - $next + 1) / $this->interval) * $this->interval;
        }

        $event = new Event($this->hook, $next, $this->recurrence, $this->args, $this->interval, LaravelQueueDriver::NAME, bin2hex(random_bytes(8)));

        CronIndex::record($event, LaravelQueueDriver::NAME);
        $driver->send(self::for($event));
    }

    /**
     * The same promise as a new job: the one a worker holds carries its queue message.
     */
    private function again(): self
    {
        return new self($this->hook, $this->args, $this->timestamp, $this->id, $this->recurrence, $this->interval, $this->site);
    }

    /**
     * Run in the site that scheduled the event: on a multisite network the queue and its worker are shared.
     */
    private function inSite(callable $run): void
    {
        $switch = function_exists('is_multisite') && is_multisite() && $this->site !== get_current_blog_id();

        if ($switch) {
            switch_to_blog($this->site);
        }

        try {
            $run();
        } finally {
            if ($switch) {
                restore_current_blog();
            }
        }
    }
}
