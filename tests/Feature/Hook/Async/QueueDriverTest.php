<?php

declare(strict_types=1);

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Infrastructure\Async\QueueDriver;
use Pollora\Hook\Infrastructure\Jobs\RunAsyncAction;

beforeEach(function (): void {
    config([
        'queue.default' => 'database',
        'queue.connections.database' => ['driver' => 'database'],
        'queue.connections.redis' => ['driver' => 'redis'],
        'queue.connections.sync' => ['driver' => 'sync'],
        'queue.connections.null' => ['driver' => 'null'],
        'hooks.async.queue' => ['connection' => null, 'queue' => 'default'],
    ]);
});

function queueDriver(): QueueDriver
{
    return new QueueDriver(app(Dispatcher::class), app('config'));
}

function queuePayload(?string $queue = null): AsyncPayload
{
    return new AsyncPayload(
        id: AsyncPayload::newId(),
        hook: 'save_post',
        handler: 'App\Hooks\Sync@handle',
        priority: 10,
        arguments: [42],
        origin: ['userId' => 1, 'blogId' => 1, 'locale' => 'fr_FR', 'dispatchedAt' => '2026-10-07T10:00:00+00:00'],
        queue: $queue,
    );
}

describe('available()', function (): void {
    it('is available on a real queue connection', function (string $connection): void {
        config(['queue.default' => $connection]);

        expect(queueDriver()->available())->toBeTrue();
    })->with(['database', 'redis']);

    it('is never available on a connection that would run the handler in the request, or drop it', function (string $connection): void {
        config(['queue.default' => $connection]);

        expect(queueDriver()->available())->toBeFalse();
    })->with(['sync', 'null', 'missing']);

    it('follows the connection set for async actions before the default one', function (): void {
        config(['queue.default' => 'sync', 'hooks.async.queue.connection' => 'redis']);

        expect(queueDriver()->available())->toBeTrue();
    });
});

describe('dispatch()', function (): void {
    beforeEach(function (): void {
        Bus::fake();
    });

    it('dispatches a RunAsyncAction job carrying the payload, on the configured connection and queue', function (): void {
        $payload = queuePayload();

        queueDriver()->dispatch($payload);

        Bus::assertDispatched(RunAsyncAction::class, fn (RunAsyncAction $job): bool => $job->payload === $payload->toJson()
            && $job->connection === 'database'
            && $job->queue === 'default'
            && $job->delay === null);
    });

    it('uses the queue name of the action and its delay', function (): void {
        config(['hooks.async.queue.connection' => 'redis']);

        queueDriver()->dispatch(queuePayload('integrations'), 30);

        Bus::assertDispatched(RunAsyncAction::class, fn (RunAsyncAction $job): bool => $job->connection === 'redis'
            && $job->queue === 'integrations'
            && $job->delay === 30);
    });
});
