<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Pollora\Modules\Infrastructure\Activation\ConnectorActivator;
use Pollora\Modules\Infrastructure\Activation\JsonStateConnector;
use Pollora\Modules\Infrastructure\Activation\ModuleConnectors;
use Throwable;

/**
 * Prepare a switch of module activation connector: show where the states live
 * now, copy them into the new connector with --import, and say what to change.
 */
#[Description('Switch where module states are stored (json, database, config, or your own)')]
#[Signature('pollora:module:connector {connector? : Connector to switch to}
    {--import : Copy the current module states into the new connector first}')]
class ModuleConnectorCommand extends Command
{
    public function handle(): int
    {
        $current = $this->currentStates();
        $currentName = (string) config('modules.connector', 'json');

        $this->components->twoColumnDetail('Activator', (string) config('modules.activator'));
        $this->components->twoColumnDetail('Connector', $this->usesConnectorActivator() ? $currentName : 'json (nwidart FileActivator)');

        foreach ($current as $module => $enabled) {
            $this->components->twoColumnDetail('  '.$module, $enabled ? '<fg=green>enabled</>' : 'disabled');
        }

        $target = $this->argument('connector');

        if (! is_string($target) || $target === '') {
            return self::SUCCESS;
        }

        try {
            $connector = (new ModuleConnectors($this->laravel))->make($target);
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        if ($this->option('import')) {
            if (! $connector->writable()) {
                $this->components->error(sprintf('The %s connector cannot be written now (%s): nothing was imported.', $target, $connector->label()));

                return self::FAILURE;
            }

            foreach ($current as $module => $enabled) {
                $connector->set($module, $enabled);
            }

            $this->components->info(sprintf('%d module state(s) copied into the %s connector (%s).', count($current), $target, $connector->label()));
        }

        if (! $this->usesConnectorActivator()) {
            $this->components->warn("nwidart reads its activator before any service provider: publish the Pollora module config first (php artisan vendor:publish --tag=pollora-modules), which sets 'activator' => 'pollora'.");
        }

        $this->line(sprintf('  Then set <comment>MODULES_CONNECTOR=%s</comment> in .env (or `connector` in config/modules.php), and clear a cached config.', $target));

        return self::SUCCESS;
    }

    private function usesConnectorActivator(): bool
    {
        return $this->laravel->bound(ActivatorInterface::class)
            && $this->laravel->make(ActivatorInterface::class) instanceof ConnectorActivator;
    }

    /**
     * @return array<string, bool>
     */
    private function currentStates(): array
    {
        if ($this->usesConnectorActivator()) {
            /** @var ConnectorActivator $activator */
            $activator = $this->laravel->make(ActivatorInterface::class);

            return $activator->connector()->all();
        }

        return (new JsonStateConnector((string) config('modules.activators.file.statuses-file', base_path('modules_statuses.json'))))->all();
    }
}
