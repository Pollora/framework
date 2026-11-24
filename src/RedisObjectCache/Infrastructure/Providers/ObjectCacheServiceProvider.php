<?php

declare(strict_types=1);

namespace Pollora\RedisObjectCache\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Pollora\ObjectCache\Application\ObjectCache;
use Pollora\ObjectCache\Infrastructure\Config\Settings;
use Pollora\ObjectCache\Infrastructure\Management\Manager;
use Pollora\ObjectCache\Infrastructure\WordPress\Configuration;
use Pollora\ObjectCache\Infrastructure\WordPress\EngineBuilder;
use Pollora\ObjectCache\Infrastructure\WordPress\Environment;
use Pollora\RedisObjectCache\Infrastructure\Cache\ObjectCacheStore;
use Pollora\RedisObjectCache\Infrastructure\Laravel\SettingsFactory;
use Pollora\RedisObjectCache\UI\Console\DisableCommand;
use Pollora\RedisObjectCache\UI\Console\EnableCommand;
use Pollora\RedisObjectCache\UI\Console\FlushCommand;
use Pollora\RedisObjectCache\UI\Console\StatusCommand;
use ReflectionClass;

/**
 * Wires `pollora/object-cache` into a Pollora project.
 *
 * The package does not know Laravel: this provider gives it the settings (the Redis
 * connection of `config/database.php`, the `pollora.object-cache` options), the
 * `pollora:object-cache:*` commands and the `wordpress` cache driver.
 *
 * The settings are registered in `register()`, not `boot()`: WordPress loads the
 * `object-cache.php` drop-in while Laravel is registered but not yet booted.
 */
final class ObjectCacheServiceProvider extends ServiceProvider
{
    /** The cache driver name to put in `config/cache.php` (`'driver' => 'wordpress'`). */
    private const string DRIVER = 'wordpress';

    /** What `php artisan cache:clear` dispatches once it has emptied a store. */
    private const string CACHE_CLEARED = 'cache:cleared';

    public function register(): void
    {
        $config = $this->app->make('config');
        $config->set('pollora.object-cache', self::merge(
            self::merge($this->packageDefaults(), require dirname(__DIR__, 4).'/config/object-cache.php'),
            (array) $config->get('pollora.object-cache', []),
        ));

        $this->app->singleton(Settings::class, fn (Application $app): Settings => Settings::fromArray($this->settings($app)));

        Configuration::provide(fn (): array => $this->settings($this->app));

        $this->app->bind(ObjectCache::class, fn (Application $app): ObjectCache => EngineBuilder::build($app->make(Settings::class), Environment::current()));

        $this->app->singleton(Manager::class, fn (Application $app): Manager => Manager::forWordPress($this->environment($app)));
    }

    /**
     * Register the commands and the `wordpress` cache driver, empty the object cache along
     * with `cache:clear`, and empty the request cache before each queued job.
     *
     * A worker lives for hours: without this, every job would start with the values the
     * previous one cached, and a Redis outage would last until the worker restarts.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([EnableCommand::class, DisableCommand::class, StatusCommand::class, FlushCommand::class]);

            $this->publishes([dirname(__DIR__, 4).'/config/object-cache.php' => $this->app->configPath('object-cache.php')], 'object-cache-config');
        }

        $this->registerCacheDriver();

        $events = $this->app->make('events');

        $events->listen(JobProcessing::class, static function (): void {
            if (function_exists('wp_cache_flush_runtime')) {
                wp_cache_flush_runtime();
            }
        });

        $events->listen(self::CACHE_CLEARED, $this->flushWithCacheClear(...));
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(Application $app): array
    {
        return SettingsFactory::make($app->make('config'), defined('WP_CACHE_KEY_SALT') && is_string(constant('WP_CACHE_KEY_SALT')) ? constant('WP_CACHE_KEY_SALT') : null);
    }

    /**
     * The WordPress environment, with the content directory of the project when WordPress does not say.
     */
    private function environment(Application $app): Environment
    {
        $current = Environment::current();

        return new Environment(
            $current->server,
            $current->cookies,
            $current->sapi,
            $current->dynamic,
            $current->salt,
            $current->database,
            $current->host,
            $current->tablePrefix,
            $current->multisite,
            $current->blogId,
            defined('WP_CONTENT_DIR') ? (string) constant('WP_CONTENT_DIR') : $app->basePath('public/content'),
        );
    }

    /**
     * The defaults the package ships.
     *
     * @return array<string, mixed>
     */
    private function packageDefaults(): array
    {
        return require dirname((string) (new ReflectionClass(Settings::class))->getFileName(), 4).'/config/object-cache.php';
    }

    /**
     * Lay `$override` over `$base`: keyed arrays are merged key by key, anything else (a value, a list) replaces.
     *
     * `mergeConfigFrom()` merges the first level only, so a `prefetch` array set here would hide the
     * keys of the package's own.
     *
     * @param  array<array-key, mixed>  $base
     * @param  array<array-key, mixed>  $override
     * @return array<array-key, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base[$key] = is_array($value) && ! array_is_list($value) && is_array($base[$key] ?? null) && ! array_is_list($base[$key])
                ? self::merge($base[$key], $value)
                : $value;
        }

        return $base;
    }

    /**
     * Make `Cache::store('wordpress')` available to the project's cache stores.
     */
    private function registerCacheDriver(): void
    {
        $this->callAfterResolving('cache', function (): void {
            Cache::extend(self::DRIVER, function (Application $app, array $config) {
                $group = $app->make('config')->get('pollora.object-cache.laravel.group', 'laravel');

                return Cache::repository(new ObjectCacheStore($app->make(ObjectCache::class), is_string($group) && $group !== '' ? $group : 'laravel'), $config);
            });
        });
    }

    /**
     * Empty the site's object cache when `cache:clear` empties another store.
     *
     * A `wordpress` store already took its own group away, so nothing more goes for it.
     * Only this site's keys are removed, never the Redis database, and an unreachable Redis
     * does not fail the command: the cache degrades.
     *
     * @param  string|null  $store  The store the command was asked to clear; null for the default one.
     */
    private function flushWithCacheClear(?string $store = null): void
    {
        $config = $this->app->make('config');

        if (! (bool) $config->get('pollora.object-cache.laravel.flush_with_cache_clear', true)) {
            return;
        }

        $name = $store ?? $config->get('cache.default');

        if ($config->get('cache.stores.'.(is_string($name) ? $name : '').'.driver') === self::DRIVER) {
            return;
        }

        $this->app->make(ObjectCache::class)->flush();

        if (function_exists('wp_cache_flush_runtime')) {
            wp_cache_flush_runtime();
        }
    }
}
