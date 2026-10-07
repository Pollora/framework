<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\Hook\Application\Services\AsyncDeclarationFailures;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Async\Contracts\AsyncDriver;
use Pollora\Hook\Async\QueuedHandler;
use Pollora\Hook\Infrastructure\Checks\AsyncActionsCheck;
use Pollora\Hook\Infrastructure\Services\AsyncInspector;

final class AsyncCheckHandler {}

/**
 * What the inspector reports, set by each test.
 */
final class AsyncCheckInspector extends AsyncInspector
{
    /** @var list<QueuedHandler> */
    public array $registered = [];

    public bool $cronOnPageLoad = false;

    /** @var array{count: int, oldest: int|null} */
    public array $overdue = ['count' => 0, 'oldest' => null];

    /** @var array{count: int, oldest: int|null}|null */
    public ?array $waiting = null;

    public int $stranded = 0;

    public int $locks = 0;

    public function registrations(): array
    {
        return $this->registered;
    }

    public function cronRunsOnPageLoad(): bool
    {
        return $this->cronOnPageLoad;
    }

    public function overdueCronEvents(int $seconds): array
    {
        return $this->overdue;
    }

    public function strandedPayloads(int $seconds): int
    {
        return $this->stranded;
    }

    public function expiredLocks(int $seconds): int
    {
        return $this->locks;
    }

    public function waitingJobs(string $connection, int $seconds): ?array
    {
        return $this->waiting;
    }
}

function asyncCheckDriver(bool $available): AsyncDriver
{
    return new readonly class($available) implements AsyncDriver
    {
        public function __construct(private bool $available) {}

        public function available(): bool
        {
            return $this->available;
        }

        public function dispatch(AsyncPayload $payload, int $delay = 0): void {}
    };
}

beforeEach(function (): void {
    Async::flush();
    Functions\when('did_action')->justReturn(1);
    Async::extend('wp-cron', fn (): AsyncDriver => asyncCheckDriver(true));
    Async::setDefaultDriver('wp-cron');

    $this->failures = new AsyncDeclarationFailures;
    $this->inspector = new AsyncCheckInspector(resolve('config'), resolve('db'));
    $this->check = new AsyncActionsCheck($this->failures, $this->inspector, resolve('config'));

    $this->actions = new Action;
    $this->register = function (string $hook, ?string $via = null): void {
        $pending = $this->actions->add($hook, [new AsyncCheckHandler, 'handle'])->async();
        if ($via !== null) {
            $pending->via($via);
        }

        $this->inspector->registered[] = $this->actions->callbacks($hook)[0]['callback'];
    };
});

afterEach(function (): void {
    Async::flush();
});

it('waits for WordPress', function (): void {
    Functions\when('did_action')->justReturn(0);

    expect($this->check->run(RunContext::Console)->status)->toBe(CheckStatus::Skipped);
});

it('passes when nothing is asynchronous', function (): void {
    expect($this->check->run(RunContext::Console)->summary)->toBe('No asynchronous action registered.');
});

it('passes with the registrations and the default driver', function (): void {
    ($this->register)('save_post');

    $result = $this->check->run(RunContext::Http);

    expect($result->status)->toBe(CheckStatus::Ok)
        ->and($result->summary)->toBe('1 asynchronous action(s), default driver "wp-cron".');
});

it('lists the #[Async] declarations discovery ignored', function (): void {
    $this->failures->fail('\\App\\Hooks\\Crm', 'sync', "except names 'save_post_page', which is not a hook of its #[Action] (save_post)");

    $result = $this->check->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Error)
        ->and($result->details)->toBe(["App\\Hooks\\Crm::sync(): #[Async] is ignored, the action runs synchronously — except names 'save_post_page', which is not a hook of its #[Action] (save_post)"])
        ->and($result->fix)->toContain('discovery:clear');
});

it('fails when the default driver is unavailable, since every handler then runs in the request', function (): void {
    Async::extend('queue', fn (): AsyncDriver => asyncCheckDriver(false));
    Async::setDefaultDriver('queue');
    ($this->register)('save_post');

    $result = $this->check->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Error)
        ->and($result->details[0])->toStartWith('The default driver "queue" is unavailable');
});

it('warns about a registration whose driver is unavailable', function (): void {
    ($this->register)('save_post', 'action-scheduler');

    $result = $this->check->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Warning)
        ->and($result->details[0])->toStartWith('save_post ask(s) for the driver "action-scheduler", unavailable, and go(es) through "wp-cron" instead');
});

it('warns when HOOKS_ASYNC_CONNECTION names a connection the queue driver cannot use', function (array $connections, string $expected): void {
    config(['hooks.async.queue.connection' => 'jobs', 'queue.connections' => $connections]);
    ($this->register)('save_post');

    $result = $this->check->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Warning)
        ->and($result->details[0])->toContain($expected);
})->with([
    'undefined' => [[], 'which config/queue.php does not define'],
    'sync' => [['jobs' => ['driver' => 'sync']], 'runs jobs inside the request'],
    'null' => [['jobs' => ['driver' => 'null']], 'discards jobs'],
]);

it('warns when WP-Cron events are overdue, with the fix for DISABLE_WP_CRON', function (bool $onPageLoad, string $fix): void {
    ($this->register)('save_post');
    $this->inspector->cronOnPageLoad = $onPageLoad;
    $this->inspector->overdue = ['count' => 3, 'oldest' => 1_800_000_000];

    $result = $this->check->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Warning)
        ->and($result->details[0])->toBe('3 WP-Cron event(s) overdue, the oldest since 2027-01-15 08:00 UTC: WP-Cron and Action Scheduler run nothing until wp-cron.php is requested')
        ->and($result->fix)->toContain($fix);
})->with([
    'disabled' => [false, 'DISABLE_WP_CRON is set'],
    'on page load' => [true, 'too few visits'],
]);

it('leaves overdue WP-Cron events alone when no driver in use relies on WP-Cron', function (): void {
    Async::extend('queue', fn (): AsyncDriver => asyncCheckDriver(true));
    Async::setDefaultDriver('queue');
    ($this->register)('save_post');
    $this->inspector->overdue = ['count' => 3, 'oldest' => 1_800_000_000];

    expect($this->check->run(RunContext::Console)->status)->toBe(CheckStatus::Ok);
});

it('warns when queued jobs wait for a worker', function (): void {
    Async::extend('queue', fn (): AsyncDriver => asyncCheckDriver(true));
    config(['queue.default' => 'database']);
    ($this->register)('save_post', 'queue');
    $this->inspector->waiting = ['count' => 2, 'oldest' => 1_800_000_000];

    $result = $this->check->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Warning)
        ->and($result->details[0])->toStartWith('2 job(s) of the "database" queue connection waiting since')
        ->and($result->fix)->toContain('php artisan queue:work database');
});

it('warns about payloads and locks the daily task left behind', function (): void {
    ($this->register)('save_post');
    $this->inspector->stranded = 4;
    $this->inspector->locks = 2;

    $result = $this->check->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Warning)
        ->and($result->details)->toHaveCount(2)
        ->and($result->details[0])->toStartWith('4 stored payload(s) older than a day')
        ->and($result->details[1])->toStartWith('2 unique lock(s) expired');
});
