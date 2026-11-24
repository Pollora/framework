<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\Infrastructure\Laravel;

use Illuminate\Contracts\Config\Repository;

/**
 * Turns Laravel's configuration into the settings array the object cache package reads.
 *
 * The Redis server comes from the connection of `config/database.php` that
 * `pollora.object-cache.connection` names; what the cache needs of its own (database,
 * timeouts) comes from `pollora.object-cache.redis`. The `options` of the connection are
 * ignored on purpose: Laravel's `options.prefix` and serializer are for Laravel's own use,
 * and the object cache needs a client with neither.
 */
final class SettingsFactory
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Repository $config, ?string $salt = null): array
    {
        $settings = $config->get('pollora.object-cache', []);
        $settings = is_array($settings) ? $settings : [];

        unset($settings['connection'], $settings['laravel']);

        $name = (string) $config->get('pollora.object-cache.connection', 'default');
        $connection = $config->get('database.redis.'.$name, []);
        $connection = is_array($connection) ? $connection : [];

        $own = is_array($settings['redis'] ?? null) ? $settings['redis'] : [];

        $redis = array_filter([
            'url' => $connection['url'] ?? null,
            'host' => $connection['host'] ?? null,
            'port' => $connection['port'] ?? null,
            'path' => $connection['path'] ?? null,
            'username' => $connection['username'] ?? null,
            'password' => $connection['password'] ?? null,
            'database' => $connection['database'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $settings['redis'] = array_merge($redis, array_filter($own, static fn (mixed $value): bool => $value !== null));
        $settings['prefix'] = SitePrefix::resolve($config, $salt);

        return $settings;
    }
}
