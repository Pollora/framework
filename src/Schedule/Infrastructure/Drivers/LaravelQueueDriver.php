<?php

declare(strict_types=1);

namespace Pollora\Schedule\Infrastructure\Drivers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Pollora\Schedule\Contracts\Identifies;
use Pollora\Schedule\Contracts\ScheduleDriver;
use Pollora\Schedule\Event;
use Pollora\Schedule\Infrastructure\Jobs\RunScheduledEvent;
use Pollora\Schedule\Support\CronIndex;

/**
 * Schedules through the application's queue: each event is a job delayed until
 * it is due, and a worker runs it.
 *
 * The driver keeps no store and never reads the queue: the 'cron' index is the
 * record of what is scheduled. Cancelling is removing the entry, and a job
 * whose entry is gone, or carries another identifier, stops (see
 * RunScheduledEvent). The connection must accept delays, so "sync" and "null"
 * are not available.
 */
final readonly class LaravelQueueDriver implements Identifies, ScheduleDriver
{
    public const string NAME = 'laravel';

    /**
     * The longest delay, in seconds, a connection of this driver accepts, when config/schedule.php does not say.
     */
    private const array MAX_DELAYS = ['sqs' => 900];

    /**
     * The bus is resolved when a job is queued: the driver is built while the application is still registering its providers.
     */
    public function __construct(
        private Container $container,
        private Repository $config,
    ) {}

    public function available(): bool
    {
        $driver = $this->config->get(sprintf('queue.connections.%s.driver', $this->connection()));

        return is_string($driver) && ! in_array($driver, ['sync', 'null'], true);
    }

    public function placeIdentified(Event $event): bool
    {
        $this->send(RunScheduledEvent::for($this->withInterval($event)));

        return true;
    }

    /**
     * A recurring event runs again only when its job knows the interval: the fluent API and #[Schedule] give the name of the recurrence, WordPress's own functions give both.
     */
    private function withInterval(Event $event): Event
    {
        if ($event->recurrence === null || $event->interval !== null) {
            return $event;
        }

        $interval = (int) ((function_exists('wp_get_schedules') ? wp_get_schedules() : [])[$event->recurrence]['interval'] ?? 0);

        return $interval > 0
            ? new Event($event->hook, $event->timestamp, $event->recurrence, $event->args, $interval, $event->driver, $event->id)
            : $event;
    }

    public function once(string $hook, int $timestamp, array $args = []): bool
    {
        return $this->place(new Event($hook, $timestamp, null, $args));
    }

    public function recurring(string $hook, int $timestamp, string $recurrence, array $args = []): bool
    {
        return $this->place($this->withInterval(new Event($hook, $timestamp, $recurrence, $args)));
    }

    /**
     * What the engine removes from the index is cancelled: a job that finds no entry stops.
     */
    public function cancel(string $hook, array $args = []): int
    {
        return count(array_filter($this->events(), static fn (Event $event): bool => $event->hook === $hook && $event->args === $args));
    }

    public function clear(string $hook): int
    {
        return count(array_filter($this->events(), static fn (Event $event): bool => $event->hook === $hook));
    }

    public function next(string $hook, array $args = []): ?int
    {
        foreach ($this->events() as $event) {
            if ($event->hook === $hook && $event->args === $args) {
                return $event->timestamp;
            }
        }

        return null;
    }

    public function events(): array
    {
        return CronIndex::events(self::NAME);
    }

    /**
     * Queue the job of an event, delayed until it is due, or by the longest delay the connection accepts: the job then waits again.
     */
    public function send(RunScheduledEvent $job): void
    {
        $delay = max(0, $job->timestamp - time());
        $longest = $this->longestDelay();

        if ($longest !== null && $delay > $longest) {
            $delay = $longest;
        }

        $job->onConnection($this->connection())->onQueue($this->queue());

        if ($delay > 0) {
            $job->delay($delay);
        }

        $this->container->make(Dispatcher::class)->dispatch($job);
    }

    /**
     * Seconds after which an event that has not run is late, and the recovery sends it again.
     */
    public function lateAfter(): int
    {
        return max(60, (int) $this->config->get('schedule.laravel.late_after', 600));
    }

    /**
     * Where the identifier of a placement is not known to the engine: record the event and queue it.
     */
    private function place(Event $event): bool
    {
        $event = $event->withId(bin2hex(random_bytes(8)));

        CronIndex::record($event, self::NAME);
        $this->placeIdentified($event);

        return true;
    }

    private function connection(): string
    {
        $connection = $this->config->get('schedule.laravel.connection');

        return is_string($connection) && $connection !== '' ? $connection : (string) $this->config->get('queue.default', 'sync');
    }

    private function queue(): ?string
    {
        $queue = $this->config->get('schedule.laravel.queue');

        return is_string($queue) && $queue !== '' ? $queue : null;
    }

    private function longestDelay(): ?int
    {
        $configured = $this->config->get('schedule.laravel.max_delay');

        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        $driver = $this->config->get(sprintf('queue.connections.%s.driver', $this->connection()));

        return is_string($driver) ? (self::MAX_DELAYS[$driver] ?? null) : null;
    }
}
