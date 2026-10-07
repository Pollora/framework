<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Async\QueuedHandler;
use Pollora\Hook\Infrastructure\Services\AsyncInspector;

final class AsyncInspectorHandler {}

function asyncInspectorPayload(string $id, int $dispatchedAt): string
{
    return (new AsyncPayload(
        id: $id,
        hook: 'save_post',
        handler: AsyncInspectorHandler::class.'@handle',
        priority: 10,
        arguments: [],
        origin: ['userId' => 0, 'blogId' => 1, 'locale' => 'en_US', 'dispatchedAt' => gmdate(DATE_ATOM, $dispatchedAt)],
    ))->toJson();
}

/**
 * Stands in for $wpdb: the options rows whose name starts with the prefix asked for.
 *
 * @param  array<string, string>  $options
 */
function asyncInspectorWpdb(array $options): object
{
    return new class($options)
    {
        public string $options = 'wp_options';

        private string $prefix = '';

        public function __construct(private readonly array $rows) {}

        public function esc_like(string $text): string
        {
            return $text;
        }

        public function prepare(string $query, string $like, int $limit): string
        {
            $this->prefix = rtrim($like, '%');

            return $query;
        }

        public function get_results(string $query, string $output): array
        {
            $rows = [];
            foreach ($this->rows as $name => $value) {
                if (str_starts_with($name, $this->prefix)) {
                    $rows[] = ['option_name' => $name, 'option_value' => $value];
                }
            }

            return $rows;
        }
    };
}

beforeEach(function (): void {
    Async::flush();
    $this->inspector = new AsyncInspector(resolve('config'), resolve('db'));
    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
});

afterEach(function (): void {
    Async::flush();
    $GLOBALS['wpdb'] = $this->previousWpdb;
    unset($GLOBALS['wp_filter']);
});

it('finds the asynchronous registrations among the WordPress hooks', function (): void {
    $actions = new Action;
    $actions->add('save_post', [new AsyncInspectorHandler, 'handle'])->async();
    $queued = $actions->callbacks('save_post')[0]['callback'];

    $GLOBALS['wp_filter'] = [
        'save_post' => (object) ['callbacks' => [10 => ['a' => ['function' => $queued], 'b' => ['function' => 'strlen']]]],
        'init' => (object) ['callbacks' => [10 => ['c' => ['function' => 'strlen']]]],
    ];

    expect($this->inspector->registrations())->toBe([$queued])
        ->and($queued)->toBeInstanceOf(QueuedHandler::class);
});

it('counts the WP-Cron events overdue for longer than the grace period', function (): void {
    $now = time();
    Functions\when('_get_cron_array')->justReturn([
        $now - 7200 => ['wp_version_check' => ['k1' => []], 'pollora/async/run' => ['k2' => [], 'k3' => []]],
        $now - 4000 => ['wp_update_plugins' => ['k4' => []]],
        $now - 60 => ['recent' => ['k5' => []]],
        $now + 600 => ['future' => ['k6' => []]],
    ]);

    expect($this->inspector->overdueCronEvents(3600))->toBe(['count' => 4, 'oldest' => $now - 7200]);
});

it('counts stored payloads no event will run, and unreadable ones', function (): void {
    $old = '11111111-1111-4111-8111-111111111111';
    $scheduled = '22222222-2222-4222-8222-222222222222';
    $recent = '33333333-3333-4333-8333-333333333333';
    Functions\when('wp_next_scheduled')->alias(fn (string $hook, array $args): int|false => $args === [$scheduled] ? time() + 3600 : false);

    $GLOBALS['wpdb'] = asyncInspectorWpdb([
        'pollora_async_'.$old => asyncInspectorPayload($old, time() - 200_000),
        'pollora_async_'.$scheduled => asyncInspectorPayload($scheduled, time() - 200_000),
        'pollora_async_'.$recent => asyncInspectorPayload($recent, time() - 60),
        'pollora_async_44444444-4444-4444-8444-444444444444' => 'not a payload',
    ]);

    expect($this->inspector->strandedPayloads(86400))->toBe(2);
});

it('counts the unique locks expired for longer than the grace period', function (): void {
    $GLOBALS['wpdb'] = asyncInspectorWpdb([
        'pollora_unique_a' => (string) (time() - 200_000),
        'pollora_unique_b' => (string) (time() - 60),
        'pollora_unique_c' => (string) (time() + 600),
        'pollora_async_x' => (string) (time() - 200_000),
    ]);

    expect($this->inspector->expiredLocks(86400))->toBe(1);
});

it('counts the jobs of a database queue that no worker picked up', function (): void {
    config([
        'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'queue.connections.database' => ['driver' => 'database', 'connection' => 'testing', 'table' => 'jobs'],
    ]);
    Schema::connection('testing')->create('jobs', function (Blueprint $table): void {
        $table->id();
        $table->string('queue');
        $table->longText('payload');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
    });
    $old = time() - 3600;
    DB::connection('testing')->table('jobs')->insert([
        ['queue' => 'default', 'payload' => '{}', 'reserved_at' => null, 'available_at' => $old],
        ['queue' => 'default', 'payload' => '{}', 'reserved_at' => null, 'available_at' => $old + 60],
        ['queue' => 'default', 'payload' => '{}', 'reserved_at' => time(), 'available_at' => $old],
        ['queue' => 'default', 'payload' => '{}', 'reserved_at' => null, 'available_at' => time()],
    ]);

    expect($this->inspector->waitingJobs('database', 900))->toBe(['count' => 2, 'oldest' => $old]);
});

it('cannot count the jobs of a queue that keeps them elsewhere', function (): void {
    config(['queue.connections.redis' => ['driver' => 'redis']]);

    expect($this->inspector->waitingJobs('redis', 900))->toBeNull()
        ->and($this->inspector->waitingJobs('missing', 900))->toBeNull();
});
