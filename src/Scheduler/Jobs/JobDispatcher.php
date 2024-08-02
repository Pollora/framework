<?php
declare(strict_types=1);

namespace Pollen\Scheduler\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Pollen\Scheduler\Contracts\JobDispatcherInterface;

class JobDispatcher implements JobDispatcherInterface
{
    protected $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function dispatch($job): int
    {
        return $this->dispatcher->dispatch($job);
    }
}
