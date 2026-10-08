<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Activation;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Pollora\Modules\Domain\Contracts\ModuleStateConnector;
use RuntimeException;
use Throwable;

/**
 * States in a WordPress option (`pollora_modules` by default): a JSON map, not
 * autoloaded, that follows the site through exports and migrations.
 *
 * It is read with Laravel's database connection, which shares WordPress's
 * database and table prefix: nwidart/laravel-modules asks before WordPress
 * loads, as Bootstrap::resolveAllowedPlugins() does for active_plugins. The
 * value is JSON, so nothing is unserialized. Once WordPress is loaded, writes go
 * through update_option() so its object cache follows.
 *
 * When the database cannot be read (fresh install, no options table yet), the
 * fallback connector answers and usesFallback() says so.
 */
class DatabaseStateConnector implements ModuleStateConnector
{
    /**
     * @var array<string, bool>|null
     */
    private ?array $states = null;

    private bool $usesFallback = false;

    public function __construct(
        private readonly ConnectionResolverInterface $database,
        private readonly ModuleStateConnector $fallback,
        private readonly string $option = 'pollora_modules',
        private readonly ?string $connection = null,
    ) {}

    public function option(): string
    {
        return $this->option;
    }

    public function fallback(): ModuleStateConnector
    {
        return $this->fallback;
    }

    /**
     * Whether the last read came from the fallback connector.
     */
    public function usesFallback(): bool
    {
        $this->all();

        return $this->usesFallback;
    }

    public function all(): array
    {
        if ($this->states !== null) {
            return $this->states;
        }

        try {
            $value = $this->options()->where('option_name', $this->option)->value('option_value');
        } catch (Throwable) {
            $this->usesFallback = true;

            return $this->states = $this->fallback->all();
        }

        $decoded = is_string($value) ? json_decode($value, true) : [];

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
        try {
            $this->options()->limit(1)->count();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function persistent(): bool
    {
        return true;
    }

    public function label(): string
    {
        return 'Database';
    }

    /**
     * @param  array<string, bool>  $states
     */
    private function write(array $states): void
    {
        $value = (string) json_encode($states);

        if ($this->wordPressIsLoaded()) {
            update_option($this->option, $value, false);
        } else {
            try {
                $this->options()->updateOrInsert(
                    ['option_name' => $this->option],
                    ['option_value' => $value, 'autoload' => 'off'],
                );
            } catch (Throwable $throwable) {
                throw new RuntimeException(sprintf('Cannot write the module states to the %s option: %s', $this->option, $throwable->getMessage()), 0, $throwable);
            }
        }

        $this->states = $states;
        $this->usesFallback = false;
    }

    private function wordPressIsLoaded(): bool
    {
        return function_exists('update_option') && isset($GLOBALS['wpdb']);
    }

    private function options(): Builder
    {
        return $this->database->connection($this->connection)->table('options');
    }
}
