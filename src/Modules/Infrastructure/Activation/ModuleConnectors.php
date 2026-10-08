<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Activation;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use InvalidArgumentException;
use Pollora\Modules\Domain\Contracts\ModuleStateConnector;

/**
 * Build the connector named in config/modules.php.
 *
 * nwidart/laravel-modules builds its activator during its own register(),
 * before the application's providers register: a project connector is a class
 * in `connectors.<name>.class`, or a factory given to extend() in
 * bootstrap/app.php, never from a service provider.
 */
class ModuleConnectors
{
    /**
     * @var array<string, Closure(Application, array<string, mixed>): ModuleStateConnector>
     */
    private static array $factories = [];

    /**
     * Register a connector factory: fn (Application $app, array $config): ModuleStateConnector.
     *
     * @param  Closure(Application, array<string, mixed>): ModuleStateConnector  $factory
     */
    public static function extend(string $name, Closure $factory): void
    {
        self::$factories[$name] = $factory;
    }

    public static function forgetExtensions(): void
    {
        self::$factories = [];
    }

    public function __construct(private readonly Application $app) {}

    /**
     * The connector config/modules.php selects.
     */
    public function selected(): ModuleStateConnector
    {
        return $this->make((string) $this->config('modules.connector', 'json'));
    }

    public function make(string $name, array $seen = []): ModuleStateConnector
    {
        if (in_array($name, $seen, true)) {
            throw new InvalidArgumentException(sprintf('Module connector "%s" falls back on itself.', $name));
        }

        $config = (array) $this->config('modules.connectors.'.$name, []);

        if (isset(self::$factories[$name])) {
            return (self::$factories[$name])($this->app, $config);
        }

        if (isset($config['class'])) {
            $connector = $this->app->make($config['class'], ['config' => $config]);

            if (! $connector instanceof ModuleStateConnector) {
                throw new InvalidArgumentException(sprintf('Module connector "%s" must implement %s.', $name, ModuleStateConnector::class));
            }

            return $connector;
        }

        return match ($name) {
            'json' => new JsonStateConnector((string) ($config['path'] ?? $this->app->basePath('modules_statuses.json'))),
            'database' => new DatabaseStateConnector(
                $this->app->make(ConnectionResolverInterface::class),
                $this->make((string) ($config['fallback'] ?? 'json'), [...$seen, $name]),
                (string) ($config['option'] ?? 'pollora_modules'),
                isset($config['connection']) ? (string) $config['connection'] : null,
            ),
            'config' => new ConfigStateConnector(
                (array) ($config['states'] ?? $this->config('modules.states', [])),
                self::names($config['enabled'] ?? []),
                self::names($config['disabled'] ?? []),
            ),
            default => throw new InvalidArgumentException(sprintf('Unknown module connector "%s": declare connectors.%s.class in config/modules.php.', $name, $name)),
        };
    }

    /**
     * Module names from a list or a comma-separated string (an env value).
     *
     * @return list<string>
     */
    public static function names(mixed $names): array
    {
        $names = is_string($names) ? explode(',', $names) : (array) $names;

        return array_values(array_filter(array_map(fn (mixed $name): string => trim((string) $name), $names), fn (string $name): bool => $name !== ''));
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->app->make('config')->get($key, $default);
    }
}
