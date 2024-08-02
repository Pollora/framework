<?php

declare(strict_types=1);

namespace Pollen\Scheduler;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;

class JobDispatcher
{
    protected $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function dispatch($job): int
    {
        $jobId = $this->dispatcher->dispatch($job);
        return $jobId;
    }
}
