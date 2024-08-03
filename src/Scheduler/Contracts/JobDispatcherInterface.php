<?php

declare(strict_types=1);

namespace Pollen\Scheduler\Contracts;

interface JobDispatcherInterface
{
    public function dispatch($job): int;
}
