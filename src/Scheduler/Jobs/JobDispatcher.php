<?php

declare(strict_types=1);

namespace Pollora\Scheduler\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Pollora\Scheduler\Contracts\JobDispatcherInterface;

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
