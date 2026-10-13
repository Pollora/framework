<?php

declare(strict_types=1);

namespace Pollora\Schedule\Application\Services;

use Illuminate\Contracts\Config\Repository;
use Pollora\Schedule\Event;
use Pollora\Schedule\Schedule;
use Pollora\Schedule\Support\CronIndex;

/**
 * Where the site's scheduled events are, against where the configuration says they should be.
 *
 * Shared by the commands, the doctor check and the admin notice, so they all
 * say the same thing.
 */
final readonly class ScheduleStatus
{
    public function __construct(private Repository $config) {}

    /**
     * The driver config/schedule.php asks for.
     */
    public function configured(): string
    {
        $driver = $this->config->get('schedule.driver', CronIndex::WP_CRON);

        return is_string($driver) && $driver !== '' ? $driver : CronIndex::WP_CRON;
    }

    /**
     * The driver new events go to now.
     */
    public function active(): string
    {
        return Schedule::engine()->driverName();
    }

    /**
     * Whether the configured driver is one the package does not know: the site runs on WP-Cron instead.
     */
    public function unknown(): bool
    {
        return $this->configured() !== $this->active();
    }

    /**
     * Whether the driver is not there (Action Scheduler without a plugin that bundles it): events stay on WP-Cron.
     */
    public function unavailable(): bool
    {
        return $this->active() !== CronIndex::WP_CRON && ! Schedule::engine()->driver()->available();
    }

    /**
     * Events still kept by a driver other than the active one, by driver.
     *
     * An event on WP-Cron because its hook is protected, or because the driver
     * is not there, is where it is meant to be.
     *
     * @return array<string, int>
     */
    public function toMigrate(): array
    {
        $counts = [];

        foreach (Schedule::engine()->index() as $event) {
            if ($this->placement($event) === 'to migrate') {
                $counts[(string) $event->driver] = ($counts[(string) $event->driver] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * The driver of an event, with what sets it apart from the site's: "wp-cron (protected)", "wp-cron (to migrate)".
     */
    public function label(Event $event): string
    {
        $placement = $this->placement($event);

        return $event->driver.($placement === 'active' ? '' : sprintf(' (%s)', $placement));
    }

    /**
     * Whether an event is where the site means it to be: "active" (the driver of the site), "protected" or "fallback" (on WP-Cron on purpose), or "to migrate".
     */
    private function placement(Event $event): string
    {
        if ($event->driver === $this->active()) {
            return 'active';
        }

        if ($event->driver === CronIndex::WP_CRON && in_array($event->hook, Schedule::engine()->protectedHooks(), true)) {
            return 'protected';
        }

        if ($event->driver === CronIndex::WP_CRON && $this->unavailable()) {
            return 'fallback';
        }

        return 'to migrate';
    }
}
