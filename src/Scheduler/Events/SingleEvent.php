<?php

declare(strict_types=1);

namespace Pollora\Scheduler\Events;

/**
 * Class for handling one-time WordPress cron events.
 *
 * Manages events that need to run only once at a specific time,
 * with automatic cleanup after execution.
 *
 * @extends AbstractEvent
 */
class SingleEvent extends AbstractEvent
{
    /**
     * Handle the single event execution.
     * 
     * Executes the WordPress action and then removes the event
     * from the database.
     */
    public function handle(): void
    {
        do_action_ref_array($this->hook, $this->args);
        $this->deleteEvent();
    }
}
