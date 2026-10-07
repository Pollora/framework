<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Async;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Async\Contracts\AsyncDriver;
use Pollora\Hook\Infrastructure\Jobs\RunAsyncAction;

/**
 * Queues asynchronous actions as Laravel jobs.
 *
 * Available when the configured connection is a real queue: a "sync" or
 * "null" connection would run the handler inside the request, or never.
 */
final readonly class QueueDriver implements AsyncDriver
{
    public function __construct(
        private Dispatcher $dispatcher,
        private Repository $config,
    ) {}

    public function available(): bool
    {
        $driver = $this->config->get(sprintf('queue.connections.%s.driver', $this->connection()));

        return is_string($driver) && ! in_array($driver, ['sync', 'null'], true);
    }

    public function dispatch(AsyncPayload $payload, int $delay = 0): void
    {
        $job = (new RunAsyncAction($payload->toJson()))
            ->onConnection($this->connection())
            ->onQueue($payload->queue ?? $this->config->get('hooks.async.queue.queue', 'default'));

        if ($delay > 0) {
            $job->delay($delay);
        }

        $this->dispatcher->dispatch($job);
    }

    private function connection(): string
    {
        return (string) ($this->config->get('hooks.async.queue.connection') ?? $this->config->get('queue.default', 'sync'));
    }
}
