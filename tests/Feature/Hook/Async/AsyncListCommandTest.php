<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Async\Contracts\AsyncDriver;
use Pollora\Hook\Async\QueuedHandler;
use Pollora\Hook\Infrastructure\Providers\AsyncServiceProvider;
use Pollora\Hook\Infrastructure\Services\AsyncInspector;

final class AsyncListHandler {}

beforeEach(function (): void {
    Async::flush();
    config(['queue.default' => 'sync', 'queue.connections.sync' => ['driver' => 'sync']]);
    app()->register(AsyncServiceProvider::class, force: true);
    Async::extend('wp-cron', fn (): AsyncDriver => new class implements AsyncDriver
    {
        public function available(): bool
        {
            return true;
        }

        public function dispatch(AsyncPayload $payload, int $delay = 0): void {}
    });

    $actions = new Action;
    $actions->add('user_register', [new AsyncListHandler, 'handle'])->async();
    $actions->add('save_post', [new AsyncListHandler, 'handle'], 20)->async()->via('queue')->delay(60)->tries(3)->backoff([5, 30])->unique(600)->onQueue('crm')->asUser();

    $registered = array_map(fn (string $hook): QueuedHandler => $actions->callbacks($hook)[0]['callback'], ['user_register', 'save_post']);
    app()->instance(AsyncInspector::class, new class($registered) extends AsyncInspector
    {
        public function __construct(private readonly array $registered) {}

        public function registrations(): array
        {
            return $this->registered;
        }
    });
});

/**
 * @return array<string, mixed>
 */
function asyncListJson(): array
{
    expect(Artisan::call('pollora:async:list', ['--json' => true]))->toBe(0);

    return json_decode(Artisan::output(), true);
}

afterEach(function (): void {
    Async::flush();
});

it('lists the asynchronous actions with their options, the default driver and why', function (): void {
    $output = asyncListJson();

    expect($output['default'])->toBe(['driver' => 'wp-cron', 'source' => 'auto: the first available of action-scheduler, wp-cron'])
        ->and($output['drivers']['wp-cron'])->toBe(['available' => true, 'reason' => null])
        ->and($output['drivers']['queue']['available'])->toBeFalse()
        ->and($output['actions'])->toBe([
            ['hook' => 'save_post', 'priority' => 20, 'handler' => AsyncListHandler::class.'@handle', 'driver' => 'queue', 'delay' => 60, 'tries' => 3, 'backoff' => [5, 30], 'unique' => 600, 'queue' => 'crm', 'as_user' => true],
            ['hook' => 'user_register', 'priority' => 10, 'handler' => AsyncListHandler::class.'@handle', 'driver' => null, 'delay' => 0, 'tries' => 1, 'backoff' => [10, 60, 300], 'unique' => null, 'queue' => null, 'as_user' => false],
        ]);
});

it('names the configuration when it sets the default driver', function (): void {
    config(['hooks.async.default' => 'sync']);
    Async::setDefaultDriver('sync');

    expect(asyncListJson()['default'])->toBe(['driver' => 'sync', 'source' => 'HOOKS_ASYNC_DRIVER, config/hooks.php']);
});

it('prints a table', function (): void {
    $this->artisan('pollora:async:list')
        ->expectsOutputToContain('Default driver')
        ->expectsOutputToContain('2 asynchronous action(s)')
        ->expectsTable(
            ['Hook', 'Priority', 'Handler', 'Driver', 'Delay', 'Tries', 'Unique', 'Queue', 'As user'],
            [
                ['save_post', 20, AsyncListHandler::class.'@handle', 'queue', '60 s', '3 (backoff 5, 30 s)', '600 s', 'crm', 'yes'],
                ['user_register', 10, AsyncListHandler::class.'@handle', 'default', '—', '1', '—', '—', 'no'],
            ],
        )
        ->assertSuccessful();
});
