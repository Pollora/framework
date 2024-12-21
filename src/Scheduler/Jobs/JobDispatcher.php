<?php

declare(strict_types=1);

namespace Pollora\Scheduler\Jobs;

use Pollora\Scheduler\Contracts\JobDispatcherInterface;

/**
 * Class for dispatching WordPress cron jobs to Laravel queue.
 *
 * Handles the integration between WordPress cron events and Laravel's
 * queue system with proper dependency injection.
 *
 * @implements JobDispatcherInterface
 */
class JobDispatcher implements JobDispatcherInterface
{
    /**
     * Create a new job dispatcher instance.
     *
     * @param \Illuminate\Contracts\Bus\Dispatcher $dispatcher Laravel job dispatcher
     */
    public function __construct(protected \Illuminate\Contracts\Bus\Dispatcher $dispatcher) {}

    /**
     * Dispatch a job to the queue.
     *
     * @param mixed $job The job to dispatch
     * @return int The dispatched job ID
     */
    public function dispatch($job): int
    {
        return $this->dispatcher->dispatch($job);
    }
}
