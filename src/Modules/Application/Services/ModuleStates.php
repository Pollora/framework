<?php

declare(strict_types=1);

namespace Pollora\Modules\Application\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Pollora\Modules\Domain\Contracts\ModuleStateConnector;
use Pollora\Modules\Domain\Exceptions\ModuleLockedException;
use Pollora\Modules\Infrastructure\Activation\ConnectorActivator;
use Pollora\Modules\Infrastructure\Activation\JsonStateConnector;
use RuntimeException;

/**
 * The modules an administrator sees and switches: their state, why a switch is
 * unavailable, and where the state lives.
 *
 * Switches go through nwidart/laravel-modules (`Module::enable()` /
 * `disable()`), so its events fire and its activator writes — the connector
 * activator when config/modules.php selects it, its FileActivator otherwise.
 */
class ModuleStates
{
    public function __construct(
        private readonly Container $app,
        private readonly Repository $config,
    ) {}

    public function available(): bool
    {
        return $this->app->bound('modules');
    }

    /**
     * Where the state lives: the activator's connector, or nwidart's JSON file.
     */
    public function connector(): ModuleStateConnector
    {
        $activator = $this->activator();

        if ($activator instanceof ConnectorActivator) {
            return $activator->connector();
        }

        return new JsonStateConnector((string) $this->config->get('modules.activators.file.statuses-file', base_path('modules_statuses.json')));
    }

    /**
     * Whether administrators may switch modules at all (MODULES_ADMIN_TOGGLE).
     */
    public function togglesEnabled(): bool
    {
        return filter_var($this->config->get('modules.admin.toggle', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function capability(): string
    {
        return (string) $this->config->get('modules.admin.capability', 'activate_plugins');
    }

    /**
     * Every module, sorted by name.
     *
     * @return list<array{name: string, description: string, path: string, enabled: bool, locked: bool|null, toggleable: bool, reason: string|null}>
     */
    public function all(): array
    {
        if (! $this->available()) {
            return [];
        }

        $repository = $this->app->make('modules');
        $activator = $this->activator();
        $locks = $activator instanceof ConnectorActivator ? $activator->locks() : [];
        $writable = $this->connector()->writable();
        $togglesEnabled = $this->togglesEnabled();
        $modules = [];

        foreach ($repository->all() as $module) {
            $name = (string) $module->getName();
            $locked = $locks[$name] ?? null;

            $reason = match (true) {
                ! $togglesEnabled => __('Switching modules from the admin is turned off (MODULES_ADMIN_TOGGLE).', 'pollora'),
                $locked !== null => $locked
                    ? __('Locked enabled by config/modules.php.', 'pollora')
                    : __('Locked disabled by config/modules.php.', 'pollora'),
                ! $writable => __('The module states cannot be written here.', 'pollora'),
                default => null,
            };

            $modules[] = [
                'name' => $name,
                'description' => (string) $module->getDescription(),
                'path' => (string) $module->getPath(),
                'enabled' => (bool) $module->isEnabled(),
                'locked' => $locked,
                'toggleable' => $reason === null,
                'reason' => $reason,
            ];
        }

        usort($modules, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $modules;
    }

    /**
     * Switch a module. It applies from the next request: module providers
     * registered before anything could switch them.
     *
     * @throws RuntimeException When the module is unknown, locked or cannot be written
     */
    public function switch(string $name, bool $enable): void
    {
        $module = $this->available() ? $this->app->make('modules')->find($name) : null;

        if ($module === null) {
            throw new RuntimeException(sprintf(__('Module %s does not exist.', 'pollora'), $name));
        }

        if (! $this->togglesEnabled()) {
            throw new RuntimeException(__('Switching modules from the admin is turned off (MODULES_ADMIN_TOGGLE).', 'pollora'));
        }

        try {
            $enable ? $module->enable() : $module->disable();
        } catch (ModuleLockedException $moduleLockedException) {
            throw new RuntimeException($moduleLockedException->getMessage(), 0, $moduleLockedException);
        }
    }

    private function activator(): ?ActivatorInterface
    {
        return $this->app->bound(ActivatorInterface::class) ? $this->app->make(ActivatorInterface::class) : null;
    }
}
