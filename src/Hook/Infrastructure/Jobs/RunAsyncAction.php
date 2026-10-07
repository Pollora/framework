<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Pollora\Hook\Async\Async;

/**
 * Runs an asynchronous action in a queue worker.
 *
 * Retries are made by the package, through a new job after the backoff, so
 * the job itself is tried once; the last failure is thrown, which lands it in
 * the failed jobs table.
 */
final class RunAsyncAction implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /**
     * @param  string  $payload  The payload, as JSON
     */
    public function __construct(
        public readonly string $payload,
    ) {}

    public function handle(): void
    {
        // A worker lives across jobs: forget what WordPress cached in memory for the previous one
        if (function_exists('wp_cache_flush_runtime')) {
            wp_cache_flush_runtime();
        }

        Async::receive($this->payload, throwOnFinalFailure: true);
    }

    /**
     * Name shown by Horizon and in the failed jobs table: the hook and the handler.
     */
    public function displayName(): string
    {
        $payload = json_decode($this->payload, true);

        if (! is_array($payload) || ! is_string($payload['hook'] ?? null) || ! is_string($payload['handler'] ?? null)) {
            return self::class;
        }

        $handler = str_starts_with($payload['handler'], 'closure:') ? 'closure' : $payload['handler'];

        return sprintf('pollora/async %s %s', $payload['hook'], $handler);
    }
}
