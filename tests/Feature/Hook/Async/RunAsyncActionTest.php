<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Infrastructure\Jobs\RunAsyncAction;

final class RunAsyncActionFixture
{
    /** @var list<array<int, mixed>> */
    public static array $calls = [];

    public static bool $fails = false;

    public function handle(int $postId): void
    {
        if (self::$fails) {
            throw new RuntimeException('CRM unreachable');
        }

        self::$calls[] = [$postId];
    }
}

beforeEach(function (): void {
    Functions\when('wp_cache_flush_runtime')->justReturn(true);
    Async::flush();
    RunAsyncActionFixture::$calls = [];
    RunAsyncActionFixture::$fails = false;
    $this->incidents = [];
    Async::reportUsing(function (string $message): void {
        $this->incidents[] = $message;
    });
});

afterEach(function (): void {
    Async::flush();
});

function runAsyncActionPayload(string $handler = 'RunAsyncActionFixture@handle'): string
{
    return (new AsyncPayload(
        id: AsyncPayload::newId(),
        hook: 'save_post',
        handler: $handler,
        priority: 10,
        arguments: [42],
        origin: ['userId' => 1, 'blogId' => 1, 'locale' => 'fr_FR', 'dispatchedAt' => '2026-10-07T10:00:00+00:00'],
    ))->toJson();
}

it('runs the handler, after forgetting what WordPress cached in memory', function (): void {
    $flushes = 0;
    Functions\when('wp_cache_flush_runtime')->alias(function () use (&$flushes): bool {
        $flushes++;

        return true;
    });

    (new RunAsyncAction(runAsyncActionPayload()))->handle();

    expect(RunAsyncActionFixture::$calls)->toBe([[42]])
        ->and($flushes)->toBe(1);
});

it('throws the last failure, so the job lands in the failed jobs table, without reporting it twice', function (): void {
    RunAsyncActionFixture::$fails = true;

    expect(fn () => (new RunAsyncAction(runAsyncActionPayload()))->handle())->toThrow(RuntimeException::class, 'CRM unreachable');
    expect($this->incidents)->toBe([]);
});

it('is tried once: retries are new jobs, queued by the package after the backoff', function (): void {
    expect((new RunAsyncAction(runAsyncActionPayload()))->tries)->toBe(1);
});

it('names itself after the hook and the handler', function (string $handler, string $name): void {
    expect((new RunAsyncAction(runAsyncActionPayload($handler)))->displayName())->toBe($name);
})->with([
    'class method' => ['RunAsyncActionFixture@handle', 'pollora/async save_post RunAsyncActionFixture@handle'],
    'closure' => ['closure:'.str_repeat('a', 64).':abc', 'pollora/async save_post closure'],
]);

it('falls back to its class name for a payload it cannot read', function (): void {
    expect((new RunAsyncAction('not json'))->displayName())->toBe(RunAsyncAction::class);
});
