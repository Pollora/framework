<?php

declare(strict_types=1);

namespace Pollora\Scheduler\Contracts;

interface JobDispatcherInterface
{
    public function dispatch($job): int;
}
