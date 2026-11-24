<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Pollora\ObjectCache\Application\ObjectCache;
use Pollora\ObjectCache\Infrastructure\Config\Settings;
use Pollora\ObjectCache\Infrastructure\Management\Manager;
use Pollora\ObjectCache\Infrastructure\WordPress\Configuration;
use Pollora\ObjectCache\Infrastructure\WordPress\DropInInstaller;
use Pollora\ObjectCache\Infrastructure\WordPress\Environment;
use Pollora\RedisObjectCache\Infrastructure\Cache\ObjectCacheStore;
use Pollora\RedisObjectCache\Infrastructure\Laravel\SettingsFactory;
use Pollora\RedisObjectCache\Infrastructure\Laravel\SitePrefix;
use Pollora\RedisObjectCache\Infrastructure\Providers\ObjectCacheServiceProvider;

/**
 * A configuration with a Redis connection, as `config/database.php` would hold it.
 *
 * @param  array<string, mixed>  $objectCache
 */
function redisObjectCacheConfig(array $objectCache = []): Repository
{
    return new Repository([
        'app' => ['url' => 'https://example.test'],
        'database' => [
            'default' => 'mysql',
            'connections' => ['mysql' => ['database' => 'site', 'prefix' => 'wp_']],
            'redis' => ['default' => ['host' => '10.1.2.3', 'port' => 6380, 'password' => 'secret', 'database' => 4, 'options' => ['prefix' => 'laravel_']]],
        ],
        'pollora' => ['object-cache' => $objectCache + ['connection' => 'default', 'laravel' => ['group' => 'laravel']]],
    ]);
}

/**
 * A manager whose drop-in goes to the given directory.
 */
function redisObjectCacheManager(string $dir, string $source): Manager
{
    return new Manager(new DropInInstaller($source, $dir.'/object-cache.php'), new Environment([], [], 'cli', false, null, 'db', 'host', 'wp_', false, 1, $dir));
}

beforeEach(function (): void {
    Configuration::forget();
    $this->dir = sys_get_temp_dir().'/rob-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
    $this->source = $this->dir.'/source.php';
    file_put_contents($this->source, "<?php\n// ".DropInInstaller::MARKER." 2\n");
    $this->app->register(ObjectCacheServiceProvider::class);
    config(['pollora.object-cache.redis' => ['host' => '127.0.0.1', 'port' => 1, 'connect_timeout' => 0.2]]);
    $this->app->instance(Manager::class, redisObjectCacheManager($this->dir, $this->source));
});

afterEach(function (): void {
    Configuration::forget();

    foreach (glob($this->dir.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->dir);
});

it('builds the settings from the Redis connection, ignoring its options, and the cache own redis keys win', function (): void {
    $settings = SettingsFactory::make(redisObjectCacheConfig(['redis' => ['database' => 7, 'connect_timeout' => 0.5, 'host' => null]]));

    expect($settings['redis'])->toBe(['host' => '10.1.2.3', 'port' => 6380, 'password' => 'secret', 'database' => 7, 'connect_timeout' => 0.5])
        ->and($settings)->not->toHaveKeys(['connection', 'laravel'])
        ->and(Settings::fromArray($settings)->redis['password'])->toBe('secret');
});

it('builds the settings without any Redis connection configured', function (): void {
    $config = new Repository(['pollora' => ['object-cache' => ['connection' => 'cache', 'prefix' => 'mine']], 'database' => ['redis' => ['cache' => 'broken']]]);

    expect(SettingsFactory::make($config))->toBe(['prefix' => 'mine', 'redis' => []]);
});

it('resolves the prefix: configured, then salt, then a hash of the site', function (): void {
    $hashed = SitePrefix::resolve(redisObjectCacheConfig());

    expect(SitePrefix::resolve(redisObjectCacheConfig(['prefix' => 'mine']), 'salt'))->toBe('mine')
        ->and(SitePrefix::resolve(redisObjectCacheConfig(['prefix' => '  ']), 'salt'))->toBe('salt')
        ->and(SitePrefix::resolve(redisObjectCacheConfig(), ' '))->toBe($hashed)
        ->and($hashed)->toStartWith('pollora-')->toHaveLength(20)
        ->and(SitePrefix::resolve(new Repository(['app' => ['url' => 'https://example.test'], 'database' => ['default' => 'x', 'connections' => 'broken']])))->not->toBe($hashed);
});

it('merges the package defaults and the Laravel keys under pollora.object-cache', function (): void {
    expect(config('pollora.object-cache.connection'))->toBe('default')
        ->and(config('pollora.object-cache.laravel.group'))->toBe('laravel')
        ->and(config('pollora.object-cache.laravel.flush_with_cache_clear'))->toBeTrue()
        ->and(config('pollora.object-cache.serializer'))->toBe('php')
        ->and(config('pollora.object-cache.prefetch.enabled'))->toBeFalse()
        ->and(config('pollora.object-cache.prefetch.exclude_groups'))->toBe(['userlogins']);
});

it('reads the environment variables of the first releases, over the package defaults', function (): void {
    $names = ['OBJECT_CACHE_PREFETCH' => 'true', 'OBJECT_CACHE_PREFETCH_MAX_KEYS' => '120', 'OBJECT_CACHE_REDIS_DATABASE' => '5', 'OBJECT_CACHE_SERIALIZER' => 'igbinary', 'OBJECT_CACHE_MAX_TTL' => '3600', 'OBJECT_CACHE_PREFIX' => 'env-prefix', 'OBJECT_CACHE_LARAVEL_GROUP' => 'mine', 'OBJECT_CACHE_FLUSH_WITH_CACHE_CLEAR' => 'false', 'OBJECT_CACHE_READ_TIMEOUT' => '0.5'];

    foreach ($names as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
    }

    try {
        config(['pollora.object-cache' => []]);
        (new ObjectCacheServiceProvider($this->app))->register();
        $merged = config('pollora.object-cache');

        expect($merged['prefetch'])->toMatchArray(['enabled' => true, 'max_keys' => 120, 'ttl' => 604800, 'exclude_groups' => ['userlogins']])
            ->and($merged['redis'])->toMatchArray(['database' => 5, 'read_timeout' => 0.5, 'connect_timeout' => 1.0, 'port' => 6379])
            ->and($merged['serializer'])->toBe('igbinary')
            ->and($merged['max_ttl'])->toBe(3600)
            ->and($merged['prefix'])->toBe('env-prefix')
            ->and($merged['laravel'])->toBe(['group' => 'mine', 'flush_with_cache_clear' => false]);
    } finally {
        foreach (array_keys($names) as $name) {
            putenv($name);
            unset($_ENV[$name]);
        }
    }
});

it('keeps what the project sets over the environment, replacing a list instead of mixing it, and keeps the defaults it does not set', function (): void {
    config(['pollora.object-cache' => ['prefetch' => ['enabled' => true, 'exclude_groups' => ['a', 'b']], 'global_groups' => ['g']]]);
    (new ObjectCacheServiceProvider($this->app))->register();

    expect(config('pollora.object-cache.prefetch.enabled'))->toBeTrue()
        ->and(config('pollora.object-cache.prefetch.max_keys'))->toBe(300)
        ->and(config('pollora.object-cache.prefetch.exclude_groups'))->toBe(['a', 'b'])
        ->and(config('pollora.object-cache.global_groups'))->toBe(['g'])
        ->and(config('pollora.object-cache.laravel.group'))->toBe('laravel');
});

it('hands the drop-in the settings of the project', function (): void {
    config(['pollora.object-cache.prefix' => 'from-laravel']);

    expect(Configuration::exists())->toBeTrue()
        ->and(Configuration::settings()?->prefix)->toBe('from-laravel');
});

it('reads the groups other packages add to the prefetch exclusions when the settings are built', function (): void {
    config()->push('pollora.object-cache.prefetch.exclude_groups', 'wc_session_id');

    expect(Configuration::settings()?->prefetchExcludeGroups)->toContain('userlogins', 'wc_session_id');
});

it('binds the settings once and the object cache each time', function (): void {
    expect($this->app->make(Settings::class))->toBe($this->app->make(Settings::class))
        ->and($this->app->make(ObjectCache::class))->toBeInstanceOf(ObjectCache::class)
        ->and($this->app->make(ObjectCache::class))->not->toBe($this->app->make(ObjectCache::class));
});

it('puts the drop-in in the content directory of WordPress, or of the project', function (): void {
    $this->app->forgetInstance(Manager::class);
    $this->app->offsetUnset(Manager::class);
    $this->app->register(ObjectCacheServiceProvider::class, true);

    $manager = $this->app->make(Manager::class);

    expect($manager->status(null)->lines['Drop-in'])->toContain(defined('WP_CONTENT_DIR') ? (string) constant('WP_CONTENT_DIR') : base_path('public/content'));
});

it('registers the four commands and the config to publish', function (): void {
    expect(array_keys(Artisan::all()))->toContain('pollora:object-cache:enable', 'pollora:object-cache:disable', 'pollora:object-cache:status', 'pollora:object-cache:flush');

    $this->artisan('vendor:publish', ['--tag' => 'object-cache-config', '--force' => true])->assertExitCode(0);
    expect(file_exists(config_path('object-cache.php')))->toBeTrue();
    unlink(config_path('object-cache.php'));
});

it('installs the drop-in, says it is already there, and warns when Redis does not answer', function (): void {
    $this->artisan('pollora:object-cache:enable')->expectsOutputToContain('Installed the drop-in')->expectsOutputToContain('not serving yet')->assertExitCode(0);
    $this->artisan('pollora:object-cache:enable')->expectsOutputToContain('Already installed')->assertExitCode(0);
});

it('updates an outdated drop-in and replaces another one only when forced', function (): void {
    file_put_contents($this->dir.'/object-cache.php', "<?php\n// ".DropInInstaller::MARKER." 0\n");
    $this->artisan('pollora:object-cache:enable')->expectsOutputToContain('Updated the drop-in')->assertExitCode(0);

    file_put_contents($this->dir.'/object-cache.php', "<?php\n// another cache\n");
    $this->artisan('pollora:object-cache:enable')->expectsOutputToContain('another object cache')->assertExitCode(1);
    $this->artisan('pollora:object-cache:enable', ['--force' => true])->expectsOutputToContain('Replaced another object cache')->assertExitCode(0);
});

it('reports a drop-in that cannot be written', function (): void {
    $this->app->instance(Manager::class, new Manager(
        new DropInInstaller($this->source, $this->dir.'/missing/object-cache.php'),
        new Environment([], [], 'cli', false, null, 'db', 'host', 'wp_', false, 1, $this->dir),
    ));

    $this->artisan('pollora:object-cache:enable')->assertExitCode(1);
});

it('removes its own drop-in, has nothing to do the second time, and never removes another cache', function (): void {
    copy($this->source, $this->dir.'/object-cache.php');
    $this->artisan('pollora:object-cache:disable')->expectsOutputToContain('Removed the drop-in')->assertExitCode(0);
    $this->artisan('pollora:object-cache:disable')->expectsOutputToContain('Not installed')->assertExitCode(0);

    file_put_contents($this->dir.'/object-cache.php', "<?php\n// another cache\n");
    $this->artisan('pollora:object-cache:disable')->expectsOutputToContain('another object cache')->assertExitCode(1);
});

it('shows the status, and fails when the cache does not work', function (): void {
    $this->artisan('pollora:object-cache:status')->expectsOutputToContain('Redis')->assertExitCode(1);
});

it('says nothing was flushed when Redis cannot be reached', function (): void {
    $this->artisan('pollora:object-cache:flush')->expectsOutputToContain('nothing was flushed')->assertExitCode(1);
});

it('offers the wordpress cache driver, which degrades without Redis', function (): void {
    config(['cache.stores.wordpress' => ['driver' => 'wordpress']]);
    $store = $this->app->make('cache')->store('wordpress');

    expect($store->getStore())->toBeInstanceOf(ObjectCacheStore::class)
        ->and($store->get('missing'))->toBeNull();
});

it('uses laravel as the group of the wordpress driver when the project leaves it empty', function (): void {
    config(['cache.stores.wordpress' => ['driver' => 'wordpress'], 'pollora.object-cache.laravel.group' => '']);

    expect($this->app->make('cache')->store('wordpress')->getStore())->toBeInstanceOf(ObjectCacheStore::class);
});

it('empties the request cache before each queued job', function (): void {
    $flushed = 0;
    Brain\Monkey\Functions\when('wp_cache_flush_runtime')->alias(function () use (&$flushed): bool {
        $flushed++;

        return true;
    });

    Event::dispatch(new JobProcessing('sync', Mockery::mock(Job::class, ['payload' => []])));

    expect($flushed)->toBe(1);
});

it('empties the object cache along with cache:clear of another store, but not of its own, or when switched off', function (): void {
    $flushed = 0;
    Brain\Monkey\Functions\when('wp_cache_flush_runtime')->alias(function () use (&$flushed): bool {
        $flushed++;

        return true;
    });
    config(['cache.default' => 'array', 'cache.stores.wordpress' => ['driver' => 'wordpress']]);

    Event::dispatch('cache:cleared');
    Event::dispatch('cache:cleared', ['array']);
    Event::dispatch('cache:cleared', ['wordpress']);

    config(['pollora.object-cache.laravel.flush_with_cache_clear' => false]);
    Event::dispatch('cache:cleared', ['array']);

    expect($flushed)->toBe(2);
});
