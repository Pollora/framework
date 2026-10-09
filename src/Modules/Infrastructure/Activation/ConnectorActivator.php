<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Activation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Modules\Domain\Contracts\ModuleStateConnector;
use Pollora\Modules\Domain\Events\ModuleDisabled;
use Pollora\Modules\Domain\Events\ModuleEnabled;
use Pollora\Modules\Domain\Exceptions\ModuleLockedException;
use Throwable;

/**
 * nwidart/laravel-modules' activator, delegating to the connector config/modules.php
 * selects (`'activator' => 'pollora'`).
 *
 * A module's state is, in this order: forced by `modules.locked`, else what the
 * connector holds, else disabled. The connector is read once per request. A
 * change clears what would keep the previous state — nwidart's provider
 * manifest and the discovery cache — and applies from the next request.
 */
class ConnectorActivator implements ActivatorInterface
{
    private ?ModuleStateConnector $connector = null;

    public function __construct(private readonly Application $app) {}

    public function connector(): ModuleStateConnector
    {
        return $this->connector ??= (new ModuleConnectors($this->app))->selected();
    }

    /**
     * States forced by configuration, by module name.
     *
     * @return array<string, bool>
     */
    public function locks(): array
    {
        $config = $this->app->make('config');
        $locks = [];

        foreach (ModuleConnectors::names($config->get('modules.locked.disabled', [])) as $module) {
            $locks[$module] = false;
        }

        foreach (ModuleConnectors::names($config->get('modules.locked.enabled', [])) as $module) {
            $locks[$module] = true;
        }

        return $locks;
    }

    public function isLocked(string $module): bool
    {
        return array_key_exists($module, $this->locks());
    }

    /**
     * Whether a module is enabled: locked state, then the connector, then disabled.
     */
    public function isEnabled(string $module): bool
    {
        return $this->locks()[$module] ?? $this->connector()->all()[$module] ?? false;
    }

    public function enable(Module $module): void
    {
        $this->setActiveByName($module->getName(), true);
    }

    public function disable(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;

        return $this->isEnabled($name) === $status;
    }

    public function setActive(Module $module, bool $active): void
    {
        $this->setActiveByName($module->getName(), $active);
    }

    public function setActiveByName(string $name, bool $active): void
    {
        $locks = $this->locks();

        if (array_key_exists($name, $locks)) {
            if ($locks[$name] === $active) {
                return;
            }

            throw ModuleLockedException::for($name, $locks[$name]);
        }

        $this->connector()->set($name, $active);
        $this->clearStaleCaches();

        $this->app->make('events')->dispatch($active
            ? new ModuleEnabled($name, $this->source(), $this->userId())
            : new ModuleDisabled($name, $this->source(), $this->userId()));
    }

    public function delete(Module $module): void
    {
        if (array_key_exists($module->getName(), $this->connector()->all())) {
            $this->connector()->forget($module->getName());
            $this->clearStaleCaches();
        }
    }

    public function reset(): void
    {
        foreach (array_keys($this->connector()->all()) as $module) {
            $this->connector()->forget($module);
        }

        $this->clearStaleCaches();
    }

    /**
     * Forget nwidart's provider manifest and the discovery cache, which hold
     * the classes of the modules enabled when they were written.
     */
    private function clearStaleCaches(): void
    {
        $manifest = Str::replaceLast('services.php', 'modules.php', $this->app->getCachedServicesPath());

        if (is_file($manifest)) {
            @unlink($manifest);
        }

        if ($this->app->bound(DiscoveryManager::class)) {
            try {
                $this->app->make(DiscoveryManager::class)->clearCache();
            } catch (Throwable) {
                // A stale discovery cache is reported by pollora:doctor
            }
        }
    }

    private function source(): string
    {
        return $this->app->runningInConsole() ? 'console' : 'admin';
    }

    private function userId(): ?int
    {
        if (! function_exists('get_current_user_id')) {
            return null;
        }

        $userId = get_current_user_id();

        return $userId > 0 ? $userId : null;
    }
}
