<?php

declare(strict_types=1);

namespace Pollora\Schedule\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;
use Pollora\Schedule\Event;
use Pollora\Schedule\Infrastructure\Drivers\LaravelQueueDriver;
use Pollora\Schedule\Infrastructure\Jobs\RunScheduledEvent;
use Pollora\Schedule\Support\CronIndex;

/**
 * Sends again the events of the "laravel" driver whose job was lost.
 *
 * A job can disappear (a flushed Redis, a purged queue, a message that expired)
 * and the driver never reads the queue, so the index is checked instead: an
 * event more than "late_after" seconds past its time is queued again with the
 * same identifier, so if the first job is only waiting for a worker, whichever
 * runs second finds the entry gone or moved and stops.
 */
final readonly class LateEventRecovery
{
    /**
     * Seconds between two automatic checks.
     */
    public const int INTERVAL = 300;

    public function __construct(private LaravelQueueDriver $driver) {}

    /**
     * Queue the late events of the current site again.
     *
     * @param  bool  $force  Ignore the interval between checks and the memory of what was sent again
     * @return int Number of events sent again
     */
    public function recover(bool $force = false): int
    {
        if (! $force && ! Cache::add('pollora_schedule_recovery', 1, self::INTERVAL)) {
            return 0;
        }

        $lock = Cache::lock('pollora_schedule_recovering', 120);

        if (! $lock->get()) {
            return 0;
        }

        try {
            $sent = 0;

            foreach ($this->late() as $event) {
                if ($event->id === null) {
                    $event = $event->withId(bin2hex(random_bytes(8)));
                    CronIndex::record($event, LaravelQueueDriver::NAME);
                }

                // Sent again, then left alone for as long as it takes to count as late again
                if (! $force && ! Cache::add('pollora_schedule_resent_'.$event->id, 1, $this->driver->lateAfter())) {
                    continue;
                }

                $this->driver->send(RunScheduledEvent::for($event));
                $sent++;
            }

            return $sent;
        } finally {
            $lock->release();
        }
    }

    /**
     * The events of the index more than late_after seconds past their time; one without an identifier gets one when it is sent again.
     *
     * @return list<Event>
     */
    public function late(): array
    {
        $late = [];

        foreach (CronIndex::events(LaravelQueueDriver::NAME) as $event) {
            if ($event->timestamp >= time() - $this->driver->lateAfter()) {
                break;
            }

            $late[] = $event;
        }

        return $late;
    }
}
