<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\Infrastructure\Laravel;

use Illuminate\Contracts\Config\Repository;

/**
 * The prefix that tells this site's keys from any other site's on a shared Redis.
 *
 * Every entry point (a web request, `artisan`, WP-CLI, a queue worker) must compute
 * the same prefix, or a flush would empty nothing. So it reads Laravel's configuration,
 * the same everywhere, and not WordPress's constants, which are not all defined yet
 * when a command starts.
 */
final class SitePrefix
{
    /**
     * The configured prefix wins; then `WP_CACHE_KEY_SALT`; then a hash of the site URL,
     * the database name and the table prefix (two installs can share a database).
     *
     * @param  string|null  $salt  The value of `WP_CACHE_KEY_SALT`, when the project defines it.
     */
    public static function resolve(Repository $config, ?string $salt = null): string
    {
        $configured = $config->get('pollora.object-cache.prefix');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        if ($salt !== null && trim($salt) !== '') {
            return $salt;
        }

        $connection = $config->get('database.connections.'.$config->get('database.default'), []);
        $connection = is_array($connection) ? $connection : [];

        return 'pollora-'.substr(sha1(implode('|', [
            (string) $config->get('app.url', ''),
            (string) ($connection['database'] ?? ''),
            (string) ($connection['prefix'] ?? ''),
        ])), 0, 12);
    }
}
