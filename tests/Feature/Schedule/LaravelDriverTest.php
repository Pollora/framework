<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Schedule\Event;
use Pollora\Schedule\Infrastructure\Checks\ScheduleDriverCheck;
use Pollora\Schedule\Infrastructure\Drivers\LaravelQueueDriver;
use Pollora\Schedule\Infrastructure\Jobs\RunScheduledEvent;
use Pollora\Schedule\Infrastructure\Services\LateEventRecovery;
use Pollora\Schedule\Infrastructure\Services\ScheduleFailures;
use Pollora\Schedule\Schedule;
use Pollora\Schedule\SchedulerServiceProvider;
use Pollora\Schedule\Support\CronIndex;

beforeEach(function (): void {
    $this->cron = [];
    $this->fired = [];
    $this->wpOptions = [];

    Functions\when('_get_cron_array')->alias(fn (): array => $this->cron);
    Functions\when('_set_cron_array')->alias(function (array $cron): bool {
        $this->cron = $cron;

        return true;
    });
    Functions\when('wp_get_schedules')->justReturn(['hourly' => ['interval' => 3600], 'daily' => ['interval' => 86400]]);
    Functions\when('add_filter')->justReturn(true);
    Functions\when('add_action')->justReturn(true);
    Functions\when('remove_filter')->justReturn(true);
    Functions\when('do_action_ref_array')->alias(function (string $hook, array $args): void {
        $this->fired[] = [$hook, $args];
    });
    Functions\when('wp_cache_flush_runtime')->justReturn(true);
    Functions\when('get_current_blog_id')->justReturn(1);
    Functions\when('is_multisite')->justReturn(false);
    Functions\when('get_option')->alias(fn (string $name, mixed $default = false): mixed => $this->wpOptions[$name] ?? $default);
    Functions\when('update_option')->alias(function (string $name, mixed $value): bool {
        $this->wpOptions[$name] = $value;

        return true;
    });
    Functions\when('did_action')->justReturn(1);

    config([
        'queue.default' => 'database',
        'queue.connections.database' => ['driver' => 'database'],
        'queue.connections.sqs' => ['driver' => 'sqs'],
        'queue.connections.sync' => ['driver' => 'sync'],
        'schedule.laravel' => ['connection' => null, 'queue' => null, 'max_delay' => null, 'late_after' => 600],
    ]);

    Bus::fake();
    Functions\when('wp_clear_scheduled_hook')->justReturn(0);
    Functions\when('wp_unschedule_hook')->justReturn(0);

    foreach (['engine', 'fake', 'driverBeforeFake', 'wanted'] as $property) {
        (new ReflectionClass(Schedule::class))->setStaticPropertyValue($property, null);
    }

    app()->forgetInstance(LaravelQueueDriver::class);
    app()->forgetInstance(LateEventRecovery::class);
    config(['schedule.driver' => 'laravel', 'schedule.protected_hooks' => []]);
    app()->register(SchedulerServiceProvider::class, force: true);

    $this->driver = resolve(LaravelQueueDriver::class);
    $this->engine = Schedule::engine();
});

/**
 * @return list<RunScheduledEvent>
 */
function dispatchedJobs(): array
{
    return Bus::dispatched(RunScheduledEvent::class)->all();
}

describe('the driver', function (): void {
    it('is available on a queue a worker runs, not on sync or null', function (): void {
        expect($this->driver->available())->toBeTrue();

        config(['schedule.laravel.connection' => 'sync']);
        expect($this->driver->available())->toBeFalse();

        config(['schedule.laravel.connection' => 'missing']);
        expect($this->driver->available())->toBeFalse();
    });

    it('is built before the application has a bus: the driver is chosen while the providers register', function (): void {
        $driver = new LaravelQueueDriver(new Container, resolve('config'));

        expect($driver->available())->toBeTrue();
    });

    it('queues a delayed job and writes the entry, with its identifier, in the index', function (): void {
        $time = time() + 7200;
        $this->engine->place(new Event('digest', $time, null, ['u' => 1]));

        $job = dispatchedJobs()[0];

        expect(dispatchedJobs())->toHaveCount(1)
            ->and($job->hook)->toBe('digest')
            ->and($job->timestamp)->toBe($time)
            ->and($job->delay)->toBeBetween(7199, 7200)
            ->and($job->connection)->toBe('database')
            ->and(CronIndex::ownerOf('digest', ['u' => 1]))->toBe('laravel')
            ->and(CronIndex::idOf('digest', ['u' => 1]))->toBe($job->id)
            ->and($this->engine->next('digest', ['u' => 1]))->toBe($time);
    });

    it('queues a job for an event already due without a delay', function (): void {
        $this->engine->place(new Event('digest', time() - 5));

        expect(dispatchedJobs()[0]->delay)->toBeNull();
    });

    it('waits by the longest delay a connection accepts, and by the one configured', function (): void {
        config(['schedule.laravel.connection' => 'sqs']);
        $this->engine->place(new Event('digest', time() + 7200));

        config(['schedule.laravel.connection' => 'database', 'schedule.laravel.max_delay' => 60]);
        $this->engine->place(new Event('other', time() + 7200));

        expect(dispatchedJobs()[0]->delay)->toBe(900)
            ->and(dispatchedJobs()[1]->delay)->toBe(60);
    });

    it('cancels by taking the entry out of the index, without reading the queue', function (): void {
        $this->engine->place(new Event('digest', time() + 3600, null, ['u' => 1]));

        expect($this->engine->cancel('digest', ['u' => 1]))->toBe(1)
            ->and(CronIndex::ownerOf('digest', ['u' => 1]))->toBeNull()
            ->and($this->engine->next('digest', ['u' => 1]))->toBeNull();
    });

    it('stores a single event of the WordPress functions the same way', function (): void {
        $this->driver->once('digest', time() + 600, []);
        $this->driver->recurring('reports', time() + 600, 'daily', []);

        expect(CronIndex::events('laravel'))->toHaveCount(2)
            ->and(CronIndex::events('laravel')[0]->id)->not->toBeNull()
            ->and(dispatchedJobs()[1]->interval)->toBe(86400);
    });
});

describe('the job', function (): void {
    it('fires the hook and takes a single event out of the index', function (): void {
        $this->engine->place(new Event('digest', time() - 1, null, ['u' => 1]));

        dispatchedJobs()[0]->handle($this->driver);

        expect($this->fired)->toBe([['digest', ['u' => 1]]])
            ->and(CronIndex::events('laravel'))->toBe([]);
    });

    it('queues the next occurrence of a recurring event, never in the past', function (): void {
        $this->engine->place(new Event('reports', time() - 10000, 'hourly', [], 3600));
        $job = dispatchedJobs()[0];

        $job->handle($this->driver);

        $next = CronIndex::events('laravel')[0];

        expect($this->fired)->toHaveCount(1)
            ->and($next->timestamp)->toBeGreaterThan(time())
            ->and($next->timestamp)->toBeLessThanOrEqual(time() + 3600)
            ->and(($next->timestamp - $job->timestamp) % 3600)->toBe(0)
            ->and($next->id)->not->toBe($job->id)
            ->and(dispatchedJobs())->toHaveCount(2)
            ->and(dispatchedJobs()[1]->id)->toBe($next->id);
    });

    it('stops when its event was cancelled, moved or replaced', function (): void {
        $this->engine->place(new Event('digest', time() - 1));
        $this->engine->place(new Event('moved', time() - 1));
        $this->engine->place(new Event('replaced', time() - 1));
        [$cancelled, $moved, $replaced] = dispatchedJobs();

        $this->engine->cancel('digest');
        CronIndex::record((new Event('moved', time() + 5000))->withId($moved->id), 'laravel');
        CronIndex::record((new Event('replaced', time() - 1))->withId('another'), 'laravel');

        $cancelled->handle($this->driver);
        $moved->handle($this->driver);
        $replaced->handle($this->driver);

        expect($this->fired)->toBe([]);
    });

    it('waits again when it wakes before its time, and runs only once when sent twice', function (): void {
        $this->engine->place(new Event('digest', time() + 3600));
        $early = dispatchedJobs()[0];

        $early->handle($this->driver);

        expect($this->fired)->toBe([])
            ->and(dispatchedJobs())->toHaveCount(2)
            ->and(dispatchedJobs()[1]->id)->toBe($early->id)
            ->and(dispatchedJobs()[1]->timestamp)->toBe($early->timestamp);

        $this->cron = [];
        $this->engine->place(new Event('again', time() - 1));
        $first = dispatchedJobs()[2];
        $copy = clone $first;

        $first->handle($this->driver);
        $copy->handle($this->driver);

        expect($this->fired)->toBe([['again', []]]);
    });

    it('records a final failure, and goes on with a recurring event', function (): void {
        $this->engine->place(new Event('reports', time() - 10, 'hourly', [], 3600));
        $this->engine->place(new Event('digest', time() - 10));
        [$recurring, $single] = dispatchedJobs();

        $recurring->failed(new RuntimeException('boom'));
        $single->failed(new RuntimeException('bang'));

        expect(array_column(ScheduleFailures::recent(), 'hook'))->toBe(['reports', 'digest'])
            ->and(ScheduleFailures::recent()[1]['message'])->toBe('bang')
            ->and(array_column(CronIndex::events('laravel'), 'hook'))->toBe(['reports'])
            ->and(dispatchedJobs())->toHaveCount(3);
    });

    it('runs in the site that scheduled the event on a network', function (): void {
        $switched = [];
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('switch_to_blog')->alias(function (int $site) use (&$switched): bool {
            $switched[] = $site;

            return true;
        });
        Functions\when('restore_current_blog')->alias(function () use (&$switched): bool {
            $switched[] = 'restore';

            return true;
        });

        $this->engine->place(new Event('digest', time() - 1));
        $job = dispatchedJobs()[0];
        $job = new RunScheduledEvent($job->hook, $job->args, $job->timestamp, $job->id, null, null, 3);

        $job->handle($this->driver);

        expect($switched)->toBe([3, 'restore']);
    });
});

describe('the recovery', function (): void {
    it('sends again the events more than late_after seconds past their time, with the same identifier, once', function (): void {
        $this->engine->place(new Event('lost', time() - 1200));
        $this->engine->place(new Event('fresh', time() - 30));
        $this->engine->place(new Event('future', time() + 3000));

        $queued = count(dispatchedJobs());

        $recovery = resolve(LateEventRecovery::class);
        $sent = $recovery->recover();

        expect($sent)->toBe(1)
            ->and(dispatchedJobs())->toHaveCount($queued + 1)
            ->and(dispatchedJobs()[$queued]->hook)->toBe('lost')
            ->and(dispatchedJobs()[$queued]->id)->toBe(CronIndex::idOf('lost'))
            ->and($recovery->recover())->toBe(0)
            ->and($recovery->recover(force: true))->toBe(1);
    });

    it('gives an event of the index that has no identifier one', function (): void {
        $this->cron = [time() - 5000 => ['lost' => [md5(serialize([])) => ['schedule' => false, 'args' => [], 'driver' => 'laravel']]]];

        expect(resolve(LateEventRecovery::class)->recover(force: true))->toBe(1)
            ->and(CronIndex::idOf('lost'))->not->toBeNull();
    });

    it('runs from the command, and lists with --dry-run', function (): void {
        $this->engine->place(new Event('lost', time() - 1200));

        expect(Artisan::call('pollora:schedule:recover', ['--dry-run' => true]))->toBe(0)
            ->and(Artisan::output())->toContain('lost', '1 late event(s).')
            ->and(dispatchedJobs())->toHaveCount(1)
            ->and(Artisan::call('pollora:schedule:recover'))->toBe(0)
            ->and(Artisan::output())->toContain('1 event(s) sent again.')
            ->and(dispatchedJobs())->toHaveCount(2);
    });
});

describe('the doctor check', function (): void {
    it('warns of late events, and of a failure that left a single event out of the schedule', function (): void {
        $this->engine->place(new Event('lost', time() - 1200));
        ScheduleFailures::record('digest', 'bang');

        $result = resolve(ScheduleDriverCheck::class)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and(implode(' ', $result->details))->toContain('1 scheduled event(s) are more than 10 minutes late', '"digest" failed for good')
            ->and($result->fix)->toContain('queue:work');
    });

    it('says what to change when the queue is sync', function (): void {
        config(['schedule.laravel.connection' => 'sync']);

        $result = resolve(ScheduleDriverCheck::class)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->fix)->toContain('never sync or null');
    });
});
