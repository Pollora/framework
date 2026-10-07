<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Checks;

use Illuminate\Contracts\Config\Repository;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Hook\Application\Services\AsyncDeclarationFailures;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\Exceptions\DriverUnavailable;
use Pollora\Hook\Async\QueuedHandler;
use Pollora\Hook\Infrastructure\Services\AsyncInspector;

/**
 * Asynchronous actions reach a driver, and something runs what they queue.
 *
 * None of these failures shows: an ignored #[Async] or an unavailable driver
 * runs the handler inside the request; a queued handler nobody runs simply
 * never happens. WP-Cron and Action Scheduler wait for wp-cron.php, which
 * Pollora does not run on page loads (DISABLE_WP_CRON); the queue waits for a
 * worker.
 */
final readonly class AsyncActionsCheck implements CheckInterface
{
    /** WP-Cron depends on traffic or a system cron: an hour late means neither is there. */
    private const int CRON_GRACE = 3600;

    /** A worker picks a job up within seconds. */
    private const int QUEUE_GRACE = 900;

    /** The recovery and maintenance task runs daily. */
    private const int MAINTENANCE_GRACE = 86400;

    public function __construct(
        private AsyncDeclarationFailures $failures,
        private AsyncInspector $inspector,
        private Repository $config,
    ) {}

    public function id(): string
    {
        return 'async-actions';
    }

    public function label(): string
    {
        return 'Asynchronous actions';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('did_action') || \did_action('init') === 0) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        $failures = $this->failures->all();
        $registrations = $this->inspector->registrations();

        if ($failures === [] && $registrations === []) {
            return CheckResult::ok('No asynchronous action registered.');
        }

        /** @var list<array{0: string, 1: string}> $errors Line and fix */
        $errors = [];
        /** @var list<array{0: string, 1: string}> $warnings Line and fix */
        $warnings = [];

        foreach ($failures as $method => $reason) {
            $errors[] = [sprintf('%s: #[Async] is ignored, the action runs synchronously — %s', $method, $reason), 'Fix the #[Async] declaration named in each line, then run php artisan discovery:clear'];
        }

        $default = Async::defaultDriver();
        $defaultProblem = $this->unavailable($default);

        if ($defaultProblem !== null) {
            $errors[] = [sprintf('The default driver "%s" is unavailable, so every asynchronous action runs inside the request that fires it: %s', $default, $defaultProblem), 'Set HOOKS_ASYNC_DRIVER (config/hooks.php async.default) to a driver this site has, or to auto'];
        }

        $used = $defaultProblem === null ? [$default] : [];

        foreach ($this->requestedDrivers($registrations) as $driver => $hooks) {
            $problem = $this->unavailable($driver);

            if ($problem === null) {
                $used[] = $driver;

                continue;
            }

            $warnings[] = [sprintf('%s ask(s) for the driver "%s", unavailable, and go(es) through "%s" instead: %s', implode(', ', $hooks), $driver, $default, $problem), sprintf('Make the driver "%s" available, or remove via()', $driver)];
        }

        $warnings = [...$warnings, ...$this->connectionProblems(), ...$this->unprocessed(array_values(array_unique($used)))];

        $lines = static fn (array $problems): array => array_map(static fn (array $problem): string => $problem[0], $problems);

        if ($errors !== []) {
            return CheckResult::error(sprintf('%d asynchronous action problem(s) that change how handlers run.', count($errors)), [...$lines($errors), ...$lines($warnings)], $errors[0][1]);
        }

        if ($warnings !== []) {
            return CheckResult::warning(sprintf('%d problem(s) with asynchronous actions.', count($warnings)), $lines($warnings), $warnings[0][1]);
        }

        return CheckResult::ok(sprintf('%d asynchronous action(s), default driver "%s".', count($registrations), $default));
    }

    /**
     * Why a driver cannot queue in this request, or null.
     */
    private function unavailable(string $driver): ?string
    {
        try {
            Async::driver($driver);
        } catch (DriverUnavailable $driverUnavailable) {
            return $driverUnavailable->getMessage();
        }

        return null;
    }

    /**
     * Hooks of the registrations that name their driver, by driver.
     *
     * @param  list<QueuedHandler>  $registrations
     * @return array<string, list<string>>
     */
    private function requestedDrivers(array $registrations): array
    {
        $drivers = [];

        foreach ($registrations as $registration) {
            $driver = $registration->options->driver();

            if ($driver !== null) {
                $drivers[$driver][] = $registration->hook;
            }
        }

        return array_map(static fn (array $hooks): array => array_values(array_unique($hooks)), $drivers);
    }

    /**
     * HOOKS_ASYNC_CONNECTION names a connection the queue driver cannot use.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function connectionProblems(): array
    {
        $connection = $this->config->get('hooks.async.queue.connection');

        if ($connection === null) {
            return [];
        }

        $driver = $this->config->get(sprintf('queue.connections.%s.driver', $connection));

        if (! is_string($driver)) {
            return [[sprintf('HOOKS_ASYNC_CONNECTION names the connection "%s", which config/queue.php does not define: auto leaves the queue out', $connection), 'Set HOOKS_ASYNC_CONNECTION to a connection of config/queue.php that a worker runs']];
        }

        if (in_array($driver, ['sync', 'null'], true)) {
            return [[sprintf('HOOKS_ASYNC_CONNECTION names the "%s" connection, whose %s driver %s: auto leaves the queue out', $connection, $driver, $driver === 'sync' ? 'runs jobs inside the request' : 'discards jobs'), 'Set HOOKS_ASYNC_CONNECTION to the connection a worker runs (database, redis…)']];
        }

        return [];
    }

    /**
     * What runs WP-Cron on this site, and so what to add when nothing does.
     */
    private function cronFix(): string
    {
        if ($this->config->get('wordpress.use_laravel_scheduler')) {
            return 'WP-Cron events are Laravel jobs here (wordpress.use_laravel_scheduler): run a queue worker, php artisan queue:work';
        }

        return $this->inspector->cronRunsOnPageLoad()
            ? 'The site gets too few visits to run WP-Cron: add a system cron that requests wp-cron.php every minute'
            : 'DISABLE_WP_CRON is set (Pollora sets it): add a system cron, e.g. * * * * * curl -s https://example.com/cms/wp-cron.php, or wp cron event run --due-now';
    }

    /**
     * What the drivers in use queued and nothing ran.
     *
     * @param  list<string>  $drivers
     * @return list<array{0: string, 1: string}>
     */
    private function unprocessed(array $drivers): array
    {
        $problems = [];

        if (array_intersect($drivers, ['wp-cron', 'action-scheduler']) !== []) {
            $overdue = $this->inspector->overdueCronEvents(self::CRON_GRACE);

            if ($overdue['count'] > 0) {
                $problems[] = [
                    sprintf('%d WP-Cron event(s) overdue, the oldest since %s: WP-Cron and Action Scheduler run nothing until wp-cron.php is requested', $overdue['count'], gmdate('Y-m-d H:i', (int) $overdue['oldest']).' UTC'),
                    $this->cronFix(),
                ];
            }
        }

        if (in_array('queue', $drivers, true)) {
            $connection = (string) ($this->config->get('hooks.async.queue.connection') ?? $this->config->get('queue.default'));
            $waiting = $this->inspector->waitingJobs($connection, self::QUEUE_GRACE);

            if ($waiting !== null && $waiting['count'] > 0) {
                $problems[] = [
                    sprintf('%d job(s) of the "%s" queue connection waiting since %s: no worker picks them up', $waiting['count'], $connection, gmdate('Y-m-d H:i', (int) $waiting['oldest']).' UTC'),
                    sprintf('Run a worker, kept alive by Supervisor or systemd: php artisan queue:work %s', $connection),
                ];
            }
        }

        $stranded = $this->inspector->strandedPayloads(self::MAINTENANCE_GRACE);

        if ($stranded > 0) {
            $problems[] = [sprintf('%d stored payload(s) older than a day with no event to run them: the daily recovery task (%s) does not run', $stranded, 'pollora/async/recover'), 'Make sure WP-Cron runs: wp cron event run pollora/async/recover schedules them again'];
        }

        $locks = $this->inspector->expiredLocks(self::MAINTENANCE_GRACE);

        if ($locks > 0) {
            $problems[] = [sprintf('%d unique lock(s) expired for more than a day: the daily maintenance task (%s) does not run', $locks, 'pollora/async/recover'), 'Make sure WP-Cron runs: wp cron event run pollora/async/recover deletes them'];
        }

        return $problems;
    }
}
