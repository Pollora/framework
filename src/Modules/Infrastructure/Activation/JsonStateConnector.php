<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Activation;

use Pollora\Modules\Domain\Contracts\ModuleStateConnector;
use Pollora\Modules\Domain\Exceptions\ModuleStateException;

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
        $this->update(static fn (array $states): array => [...$states, $module => $enabled]);
    }

    public function forget(string $module): void
    {
        $this->update(static function (array $states) use ($module): array {
            unset($states[$module]);

            return $states;
        });
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
     * Change the states under an exclusive lock, from what the file holds now.
     *
     * Two requests switching two modules at once each used to write the whole
     * file from the states they had read earlier, so the later write erased
     * the earlier switch. The file is read again once the lock is held, and
     * replaced by a rename, so a reader never sees a half-written file (which
     * decodes to no state at all).
     *
     * @param  callable(array<string, bool>): array<string, bool>  $change
     */
    private function update(callable $change): void
    {
        $lock = @fopen(sys_get_temp_dir().'/pollora-module-states-'.md5($this->path).'.lock', 'c');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw ModuleStateException::cannotWriteFile($this->path);
        }

        try {
            $this->states = null;
            $states = $change($this->all());
            $temporary = $this->path.'.'.bin2hex(random_bytes(6)).'.tmp';

            if (@file_put_contents($temporary, json_encode($states, JSON_PRETTY_PRINT)) === false) {
                throw ModuleStateException::cannotWriteFile($this->path);
            }

            if (is_file($this->path)) {
                // A replaced file keeps the mode of the one it replaces
                @chmod($temporary, fileperms($this->path) & 0777);
            }

            if (! @rename($temporary, $this->path)) {
                @unlink($temporary);

                throw ModuleStateException::cannotWriteFile($this->path);
            }

            $this->states = $states;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
