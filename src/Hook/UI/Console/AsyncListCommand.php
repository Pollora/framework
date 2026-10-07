<?php

declare(strict_types=1);

namespace Pollora\Hook\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\Exceptions\DriverUnavailable;
use Pollora\Hook\Async\QueuedHandler;
use Pollora\Hook\Infrastructure\Providers\AsyncServiceProvider;
use Pollora\Hook\Infrastructure\Services\AsyncInspector;

/**
 * Lists the asynchronous actions registered by the end of the boot, with their
 * options, and the driver they go through by default and why.
 */
#[Description('List the asynchronous actions, their options and the default driver')]
#[Signature('pollora:async:list {--json : Output as JSON}')]
class AsyncListCommand extends Command
{
    /** The drivers Pollora provides. */
    private const array DRIVERS = ['queue', 'action-scheduler', 'wp-cron', 'sync'];

    public function handle(AsyncInspector $inspector, Repository $config): int
    {
        $registrations = array_map($this->describe(...), $inspector->registrations());
        usort($registrations, static fn (array $a, array $b): int => [$a['hook'], $a['priority']] <=> [$b['hook'], $b['priority']]);

        $drivers = [];
        foreach (self::DRIVERS as $name) {
            $drivers[$name] = $this->unavailable($name);
        }

        $default = ['driver' => Async::defaultDriver(), 'source' => $this->defaultSource($config)];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'default' => $default,
                'drivers' => array_map(static fn (?string $problem): array => ['available' => $problem === null, 'reason' => $problem], $drivers),
                'actions' => $registrations,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('<options=bold>Default driver</>', sprintf('<fg=green;options=bold>%s</> <fg=gray>(%s)</>', $default['driver'], $default['source']));

        foreach ($drivers as $name => $problem) {
            $this->components->twoColumnDetail('  '.$name, $problem === null ? '<fg=green>available</>' : '<fg=yellow>unavailable</> <fg=gray>'.$problem.'</>');
        }

        $this->newLine();

        if ($registrations === []) {
            $this->components->info('No asynchronous action registered.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('%d asynchronous action(s)', count($registrations)));

        $this->table(
            ['Hook', 'Priority', 'Handler', 'Driver', 'Delay', 'Tries', 'Unique', 'Queue', 'As user'],
            array_map(static fn (array $registration): array => [
                $registration['hook'],
                $registration['priority'],
                $registration['handler'],
                $registration['driver'] ?? 'default',
                $registration['delay'] === 0 ? '—' : $registration['delay'].' s',
                $registration['tries'] === 1 ? '1' : sprintf('%d (backoff %s s)', $registration['tries'], implode(', ', $registration['backoff'])),
                $registration['unique'] === null ? '—' : $registration['unique'].' s',
                $registration['queue'] ?? '—',
                $registration['as_user'] ? 'yes' : 'no',
            ], $registrations),
        );

        return self::SUCCESS;
    }

    /**
     * @return array{hook: string, priority: int, handler: string, driver: string|null, delay: int, tries: int, backoff: list<int>, unique: int|null, queue: string|null, as_user: bool}
     */
    private function describe(QueuedHandler $registration): array
    {
        $options = $registration->options;

        return [
            'hook' => $registration->hook,
            'priority' => $registration->priority,
            'handler' => $registration->label(),
            'driver' => $options->driver(),
            'delay' => $options->delayInSeconds(),
            'tries' => $options->attempts(),
            'backoff' => $options->backoffDelays(),
            'unique' => $options->uniqueFor(),
            'queue' => $options->queue(),
            'as_user' => $options->runsAsUser(),
        ];
    }

    /**
     * Where the default driver comes from, in the order Async::defaultDriver() reads them.
     */
    private function defaultSource(Repository $config): string
    {
        $configured = $config->get('hooks.async.default', 'auto');

        if (is_string($configured) && $configured !== '' && $configured !== 'auto') {
            return 'HOOKS_ASYNC_DRIVER, config/hooks.php';
        }

        if (defined(Async::DRIVER_CONSTANT) && is_string(constant(Async::DRIVER_CONSTANT)) && constant(Async::DRIVER_CONSTANT) !== '') {
            return 'the '.Async::DRIVER_CONSTANT.' constant';
        }

        if (function_exists('apply_filters') && ! in_array(apply_filters(Async::DRIVER_FILTER, Async::DEFAULT_DRIVER), [Async::DEFAULT_DRIVER, ''], true)) {
            return 'the '.Async::DRIVER_FILTER.' filter';
        }

        return 'auto: the first available of '.implode(', ', AsyncServiceProvider::autoDrivers($config));
    }

    private function unavailable(string $driver): ?string
    {
        try {
            Async::driver($driver);
        } catch (DriverUnavailable $driverUnavailable) {
            return $driverUnavailable->getMessage();
        }

        return null;
    }
}
