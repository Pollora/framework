<?php

declare(strict_types=1);

namespace Pollora\Schedule\Contracts;

/**
 * Interface for job dispatching functionality.
 *
 * Defines the contract for dispatching jobs to the queue system with
 * proper type safety and error handling.
 */
interface JobDispatcherInterface
{
    /**
     * Dispatch a job to the queue.
     *
     * @param  mixed  $job  The job to dispatch
     * @return int The dispatched job ID
     */
    public function dispatch(mixed $job): int;
}
