<?php

declare(strict_types=1);

namespace Pollen\Scheduler\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Pollen\Scheduler\Contracts\JobDispatcherInterface;

class JobDispatcher implements JobDispatcherInterface
{
    public function __construct(protected \Illuminate\Contracts\Bus\Dispatcher $dispatcher)
    {
    }

    public function dispatch($job): int
    {
        return $this->dispatcher->dispatch($job);
    }
}
