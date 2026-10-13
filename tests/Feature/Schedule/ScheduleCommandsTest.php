<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Support\Facades\Artisan;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Schedule\Application\Services\ScheduleStatus;
use Pollora\Schedule\Contracts\ScheduleDriver;
use Pollora\Schedule\Event;
use Pollora\Schedule\Infrastructure\Checks\ScheduleDriverCheck;
use Pollora\Schedule\Infrastructure\Services\MigrationNotice;
use Pollora\Schedule\Schedule;
use Pollora\Schedule\SchedulerServiceProvider;

/**
 * A driver that keeps events in memory, whose availability a test sets.
 */
final class MemoryScheduleDriver implements ScheduleDriver
{
    public static bool $available = true;

    /** @var list<Event> */
    public array $kept = [];

    public function available(): bool
    {
        return self::$available;
    }

    public function once(string $hook, int $timestamp, array $args = []): bool
    {
        $this->kept[] = new Event($hook, $timestamp, null, $args);

        return true;
    }

    public function recurring(string $hook, int $timestamp, string $recurrence, array $args = []): bool
    {
        $this->kept[] = new Event($hook, $timestamp, $recurrence, $args, 3600);

        return true;
    }

    public function cancel(string $hook, array $args = []): int
    {
        $before = count($this->kept);
        $this->kept = array_values(array_filter($this->kept, fn (Event $event): bool => $event->hook !== $hook || $event->args !== $args));

        return $before - count($this->kept);
    }

    public function clear(string $hook): int
    {
        $before = count($this->kept);
        $this->kept = array_values(array_filter($this->kept, fn (Event $event): bool => $event->hook !== $hook));

        return $before - count($this->kept);
    }

    public function next(string $hook, array $args = []): ?int
    {
        foreach ($this->kept as $event) {
            if ($event->hook === $hook && $event->args === $args) {
                return $event->timestamp;
            }
        }

        return null;
    }

    public function events(): array
    {
        return $this->kept;
    }
}

beforeEach(function (): void {
    $schedule = new ReflectionClass(Schedule::class);

    foreach (['engine', 'fake', 'driverBeforeFake', 'wanted'] as $property) {
        $schedule->setStaticPropertyValue($property, null);
    }

    MemoryScheduleDriver::$available = true;
    $this->memory = new MemoryScheduleDriver;
    $this->time = time() + 3600;
    $this->cron = [
        $this->time => [
            'reports' => [md5(serialize([])) => ['schedule' => 'daily', 'args' => [], 'interval' => 86400]],
            'wp_version_check' => [md5(serialize([])) => ['schedule' => 'twicedaily', 'args' => [], 'interval' => 43200]],
        ],
        $this->time + 60 => [
            'digest' => [md5(serialize(['u' => 1])) => ['schedule' => false, 'args' => ['u' => 1], 'driver' => 'memory']],
            'action_scheduler_run_queue' => [md5(serialize([])) => ['schedule' => 'every_minute', 'args' => [], 'interval' => 60]],
        ],
    ];

    Functions\when('_get_cron_array')->alias(fn (): array => $this->cron);
    Functions\when('_set_cron_array')->alias(function (array $cron): bool {
        $this->cron = $cron;

        return true;
    });
    Functions\when('wp_get_schedules')->justReturn(['daily' => ['interval' => 86400], 'twicedaily' => ['interval' => 43200]]);
    Functions\when('wp_schedule_event')->justReturn(true);
    Functions\when('wp_schedule_single_event')->justReturn(true);
    Functions\when('did_action')->justReturn(1);
    Functions\when('wp_date')->alias(fn (string $format, int $timestamp): string => gmdate($format, $timestamp));

    Schedule::extend('memory', fn (): ScheduleDriver => $this->memory);
    config(['schedule.driver' => 'memory', 'schedule.protected_hooks' => []]);
    app()->register(SchedulerServiceProvider::class, force: true);
});

afterEach(function (): void {
    MemoryScheduleDriver::$available = true;
});

/**
 * @return list<array<string, mixed>>
 */
function scheduleListJson(array $options = []): array
{
    expect(Artisan::call('pollora:schedule:list', [...$options, '--json' => true]))->toBe(0);

    return json_decode(Artisan::output(), true);
}

describe('driver choice', function (): void {
    it('chooses the driver config/schedule.php names, and merges the defaults', function (): void {
        expect(Schedule::engine()->driverName())->toBe('memory')
            ->and(require __DIR__.'/../../../config/schedule.php')->toMatchArray(['protected_hooks' => []])
            ->and(config('schedule.driver'))->toBe('memory');
    });

    it('falls back to WP-Cron when it names a driver that does not exist, and says so', function (): void {
        config(['schedule.driver' => 'nope']);
        $schedule = new ReflectionClass(Schedule::class);
        $schedule->setStaticPropertyValue('engine', null);

        app()->register(SchedulerServiceProvider::class, force: true);

        $status = resolve(ScheduleStatus::class);

        expect(Schedule::engine()->driverName())->toBe('wp-cron')
            ->and($status->unknown())->toBeTrue()
            ->and($status->configured())->toBe('nope');
    });

    it('does not call a driver unknown because something else switched the engine after the provider', function (): void {
        Schedule::driver('wp-cron');

        $status = app(ScheduleStatus::class);

        expect($status->configured())->toBe('memory')
            ->and($status->active())->toBe('wp-cron')
            ->and($status->unknown())->toBeFalse();
    });

    it('protects the hooks the configuration names', function (): void {
        config(['schedule.protected_hooks' => ['my_heartbeat']]);
        app()->register(SchedulerServiceProvider::class, force: true);

        expect(Schedule::engine()->protectedHooks())->toContain('my_heartbeat', 'action_scheduler_run_queue');
    });
});

describe('pollora:schedule:list', function (): void {
    it('lists every event with its type and its driver, soonest first, from the index alone', function (): void {
        $rows = scheduleListJson();

        expect(array_column($rows, 'hook'))->toBe(['reports', 'wp_version_check', 'digest', 'action_scheduler_run_queue'])
            ->and($rows[2])->toMatchArray(['type' => 'once', 'driver' => 'memory', 'driver_label' => 'memory', 'args' => ['u' => 1]])
            ->and($rows[0])->toMatchArray(['type' => 'recurring', 'recurrence' => 'daily', 'frequency' => 'daily', 'driver' => 'wp-cron']);
    });

    it('marks what is on WP-Cron on purpose, and what is left to migrate', function (): void {
        $labels = array_column(scheduleListJson(), 'driver_label', 'hook');

        expect($labels['action_scheduler_run_queue'])->toBe('wp-cron (protected)')
            ->and($labels['reports'])->toBe('wp-cron (to migrate)')
            ->and($labels['digest'])->toBe('memory');

        MemoryScheduleDriver::$available = false;

        expect(array_column(scheduleListJson(), 'driver_label', 'hook')['reports'])->toBe('wp-cron (fallback)');
    });

    it('filters by type and by driver, and refuses an unknown type', function (): void {
        expect(array_column(scheduleListJson(['--type' => 'once']), 'hook'))->toBe(['digest'])
            ->and(array_column(scheduleListJson(['--driver' => 'wp-cron', '--type' => 'recurring']), 'hook'))->toBe(['reports', 'wp_version_check', 'action_scheduler_run_queue'])
            ->and(Artisan::call('pollora:schedule:list', ['--type' => 'sometimes']))->toBe(1);
    });

    it('prints a table, and a line when nothing matches', function (): void {
        expect(Artisan::call('pollora:schedule:list'))->toBe(0)
            ->and(Artisan::output())->toContain('wp-cron (to migrate)', 'digest');

        Artisan::call('pollora:schedule:list', ['--driver' => 'nobody']);

        expect(Artisan::output())->toContain('No scheduled event matches.');
    });

    it('writes a frequency a person reads for a custom interval', function (): void {
        $this->cron[$this->time]['fast'] = [md5(serialize([])) => ['schedule' => 'custom_x', 'args' => [], 'interval' => 900]];

        expect(array_column(scheduleListJson(), 'frequency', 'hook')['fast'])->toBe('15 min');
    });
});

describe('pollora:schedule:migrate', function (): void {
    it('shows what would move, and moves nothing, with --dry-run', function (): void {
        expect(Artisan::call('pollora:schedule:migrate', ['--dry-run' => true]))->toBe(0)
            ->and(Artisan::output())->toContain('Would move reports', 'Kept action_scheduler_run_queue')
            ->and($this->memory->kept)->toBe([]);
    });

    it('moves the events of WP-Cron to the driver of the site', function (): void {
        expect(Artisan::call('pollora:schedule:migrate'))->toBe(0)
            ->and(array_column($this->memory->kept, 'hook'))->toContain('reports', 'wp_version_check')
            ->and(array_column(scheduleListJson(), 'driver_label', 'hook')['reports'])->toBe('memory');
    });

    it('moves them back with --from and --to, and guesses the source when the target is WP-Cron', function (): void {
        Artisan::call('pollora:schedule:migrate');

        expect(Artisan::call('pollora:schedule:migrate', ['--to' => 'wp-cron']))->toBe(0)
            ->and(array_column(scheduleListJson(), 'driver', 'hook')['reports'])->toBe('wp-cron');
    });

    it('fails when the target is not there, or there is no other driver to take from', function (): void {
        MemoryScheduleDriver::$available = false;

        expect(Artisan::call('pollora:schedule:migrate'))->toBe(1)
            ->and(Artisan::output())->toContain('not available');

        MemoryScheduleDriver::$available = true;
        $this->cron = [];

        expect(Artisan::call('pollora:schedule:migrate', ['--to' => 'wp-cron']))->toBe(1)
            ->and(Artisan::output())->toContain('say which one with --from');
    });
});

describe('the doctor check', function (): void {
    it('is ok when events are kept by the driver of the site', function (): void {
        $this->cron = [$this->time => ['digest' => [md5(serialize([])) => ['schedule' => false, 'args' => [], 'driver' => 'memory']]]];

        $result = resolve(ScheduleDriverCheck::class)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Ok);
    });

    it('warns of events a former driver keeps, with the command that moves them', function (): void {
        $result = resolve(ScheduleDriverCheck::class)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and(implode(' ', $result->details))->toContain('"wp-cron"', 'not by the driver of the site ("memory")')
            ->and($result->fix)->toContain('pollora:schedule:migrate --from=wp-cron --to=memory');
    });

    it('warns of a driver that is not there, and does not ask to migrate what falls back to WP-Cron', function (): void {
        MemoryScheduleDriver::$available = false;
        $this->cron = [$this->time => ['reports' => [md5(serialize([])) => ['schedule' => 'daily', 'args' => [], 'interval' => 86400]]]];

        $result = resolve(ScheduleDriverCheck::class)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and(implode(' ', $result->details))->toContain('is not available')
            ->and(implode(' ', $result->details))->not->toContain('still kept');
    });

    it('warns of a driver the configuration names and nobody provides', function (): void {
        config(['schedule.driver' => 'nope']);
        (new ReflectionClass(Schedule::class))->setStaticPropertyValue('engine', null);
        app()->register(SchedulerServiceProvider::class, force: true);

        $result = resolve(ScheduleDriverCheck::class)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and(implode(' ', $result->details))->toContain('"nope", which does not exist');
    });

    it('is skipped before WordPress has initialised', function (): void {
        Functions\when('did_action')->justReturn(0);

        expect(resolve(ScheduleDriverCheck::class)->run(RunContext::Console)->status)->toBe(CheckStatus::Skipped);
    });
});

describe('the administration notice', function (): void {
    beforeEach(function (): void {
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html')->returnArg();
    });

    it('tells those who can run the command which events wait, and how to move them', function (): void {
        Functions\when('current_user_can')->justReturn(true);

        ob_start();
        resolve(MigrationNotice::class)->render();
        $html = (string) ob_get_clean();

        expect($html)->toContain('notice-warning', 'pollora:schedule:migrate --from=wp-cron --to=memory');
    });

    it('says nothing to those who cannot, or when nothing waits', function (): void {
        Functions\when('current_user_can')->justReturn(false);

        ob_start();
        resolve(MigrationNotice::class)->render();
        expect((string) ob_get_clean())->toBe('');

        Functions\when('current_user_can')->justReturn(true);
        $this->cron = [];

        ob_start();
        resolve(MigrationNotice::class)->render();
        expect((string) ob_get_clean())->toBe('');
    });

    it('is shown on the administration screens, and the network ones', function (): void {
        $added = [];
        Functions\when('add_action')->alias(function (string $hook) use (&$added): bool {
            $added[] = $hook;

            return true;
        });

        resolve(MigrationNotice::class)->register();

        expect($added)->toBe(['admin_notices', 'network_admin_notices']);
    });
});
