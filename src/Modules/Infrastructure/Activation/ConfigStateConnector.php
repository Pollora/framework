<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Activation;

use Pollora\Modules\Domain\Contracts\ModuleStateConnector;
use Pollora\Modules\Domain\Exceptions\ModuleStateException;

/**
 * States shipped with the code: `connectors.config.states`, or the
 * MODULES_ENABLED / MODULES_DISABLED lists. Read-only, for immutable deploys.
 */
class ConfigStateConnector implements ModuleStateConnector
{
    /**
     * @param  array<string, bool>  $states
     * @param  list<string>  $enabled
     * @param  list<string>  $disabled
     */
    public function __construct(
        private readonly array $states = [],
        private readonly array $enabled = [],
        private readonly array $disabled = [],
    ) {}

    public function all(): array
    {
        $states = array_map(fn (mixed $state): bool => (bool) $state, $this->states);

        foreach ($this->enabled as $module) {
            $states[$module] = true;
        }

        foreach ($this->disabled as $module) {
            $states[$module] = false;
        }

        return $states;
    }

    public function set(string $module, bool $enabled): void
    {
        throw ModuleStateException::comesFromConfiguration();
    }

    public function forget(string $module): void
    {
        $this->set($module, false);
    }

    public function writable(): bool
    {
        return false;
    }

    public function persistent(): bool
    {
        return true;
    }

    public function label(): string
    {
        return 'Configuration';
    }
}
