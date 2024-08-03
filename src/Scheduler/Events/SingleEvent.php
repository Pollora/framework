<?php

declare(strict_types=1);

namespace Pollen\Scheduler\Events;

class SingleEvent extends AbstractEvent
{
    public function handle(): void
    {
        do_action_ref_array($this->hook, $this->args);
        $this->deleteEvent();
    }
}
