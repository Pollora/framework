<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Activation;

use Pollora\Modules\Domain\Contracts\ModuleStateConnector;
use RuntimeException;

/**
 * States in modules_statuses.json, in nwidart/laravel-modules' own format: the
 * file its FileActivator reads and writes, so switching activator changes nothing.
 */
class JsonStateConnector implements ModuleStateConnector
{
    /**
     * @var array<string, bool>|null
     */
    private ?array $states = null;

    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function all(): array
    {
        if ($this->states !== null) {
            return $this->states;
        }

        $decoded = is_file($this->path) ? json_decode((string) file_get_contents($this->path), true) : [];

        return $this->states = array_map(fn (mixed $state): bool => (bool) $state, is_array($decoded) ? $decoded : []);
    }

    public function set(string $module, bool $enabled): void
    {
        $this->write([...$this->all(), $module => $enabled]);
    }

    public function forget(string $module): void
    {
        $states = $this->all();
        unset($states[$module]);

        $this->write($states);
    }

    public function writable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    public function persistent(): bool
    {
        return false;
    }

    public function label(): string
    {
        return 'JSON file';
    }

    /**
     * @param  array<string, bool>  $states
     */
    private function write(array $states): void
    {
        if (@file_put_contents($this->path, json_encode($states, JSON_PRETTY_PRINT)) === false) {
            throw new RuntimeException(sprintf('Cannot write the module states to %s.', $this->path));
        }

        $this->states = $states;
    }
}
