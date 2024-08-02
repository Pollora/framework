<?php

declare(strict_types=1);

namespace Pollen\Scheduler;

class WordPressSingleEvent extends WordPressEvent
{
    public function handle()
    {
        // Exécute le hook WordPress
        do_action_ref_array($this->hook, $this->args);
    }
}
