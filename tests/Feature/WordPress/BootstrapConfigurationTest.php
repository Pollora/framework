<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Support\Facades\Constant;
use Pollora\WordPress\Bootstrap;
use Pollora\WordPress\Config\ConstantManager;

/**
 * A constant manager that records what Bootstrap queues instead of defining
 * real constants, which would stay for the rest of the process.
 */
final class ContentRecordingConstantManager extends ConstantManager
{
    /** @var array<string, mixed> */
    public array $queued = [];

    public int $applied = 0;

    public function queue(string $key, mixed $value): void
    {
        $this->queued[$key] = $value;
    }

    public function apply(): void
    {
        $this->applied++;
    }
}

function contentBootstrap(bool $console = false, bool $wpCli = false, bool $debug = false, ?Action $action = null): Bootstrap
{
    $detector = Mockery::mock(ConsoleDetectionService::class);
    $detector->shouldReceive('isConsole')->andReturn($console);
    $detector->shouldReceive('isWpCli')->andReturn($wpCli);

    $debugDetector = Mockery::mock(DebugDetectorInterface::class);
    $debugDetector->shouldReceive('isDebugMode')->andReturn($debug);

    return new Bootstrap($detector, $debugDetector, $action ?? Mockery::mock(Action::class));
}

function contentInvoke(object $object, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($object, $method))->invoke($object, ...$arguments);
}

/**
 * Record the filters Bootstrap adds, as [hook, callback, priority].
 *
 * @return ArrayObject<int, array{string, mixed, int}>
 */
function contentRecordFilters(): ArrayObject
{
    $added = new ArrayObject;
    Functions\when('add_filter')->alias(function (string $hook, $callback, int $priority = 10) use ($added): bool {
        $added[] = [$hook, $callback, $priority];

        return true;
    });

    return $added;
}

beforeEach(function (): void {
    $this->constants = new ContentRecordingConstantManager;
    Constant::swap($this->constants);
});

describe('Bootstrap constants', function (): void {
    it('derives the WordPress locations from the application URL and base path', function (): void {
        config()->set('app.url', 'https://example.test');
        config()->set('wordpress.constants', []);

        contentInvoke(contentBootstrap(), 'setLocationConstants');

        expect($this->constants->queued)->toMatchArray([
            'WP_PATH' => 'public/cms/',
            'WP_HOME' => 'https://example.test',
            'WP_SITEURL' => 'https://example.test/cms/',
            'WP_CONTENT_URL' => 'https://example.test/content',
        ])->and($this->constants->queued['WP_CONTENT_DIR'])->toBe($this->app->basePath().DIRECTORY_SEPARATOR.'public/content')
            ->and($this->constants->applied)->toBe(1);
    });

    it('turns the debug mode of the application into the WordPress debug constants', function (bool $debug): void {
        config()->set('wordpress.constants', []);
        $this->app->instance('request', Request::create('/'));

        contentInvoke(contentBootstrap(debug: $debug), 'defineWordPressConstants');

        expect($this->constants->queued)->toMatchArray([
            'WP_DEBUG' => $debug,
            'WP_DEBUG_DISPLAY' => $debug,
            'JETPACK_DEV_DEBUG' => $debug,
            'DISABLE_WP_CRON' => true,
            'WP_AUTO_UPDATE_CORE' => false,
            'WP_POST_REVISIONS' => 5,
        ]);
    })->with([true, false]);

    it('serves themes on the front but not in the console nor under /cms/', function (bool $console, string $uri, bool $expected): void {
        config()->set('wordpress.constants', []);
        $this->app->instance('request', Request::create($uri));

        contentInvoke(contentBootstrap(console: $console), 'defineWordPressConstants');

        expect($this->constants->queued['WP_USE_THEMES'])->toBe($expected);
    })->with([
        'front request' => [false, '/blog', true],
        'WordPress admin area' => [false, '/cms/wp-admin/', false],
        'console' => [true, '/', false],
    ]);

    it('upper-cases the constants the configuration declares', function (): void {
        config()->set('wordpress.constants', ['my_flag' => 'on', 'WP_POST_REVISIONS' => 9]);
        $this->app->instance('request', Request::create('/'));

        contentInvoke(contentBootstrap(), 'defineWordPressConstants');

        expect($this->constants->queued)->toMatchArray(['MY_FLAG' => 'on', 'WP_POST_REVISIONS' => 9]);
    });

    it('describes the database to WordPress with the host and port joined', function (): void {
        $bootstrap = contentBootstrap();
        (new ReflectionProperty(Bootstrap::class, 'db'))->setValue($bootstrap, [
            'database' => 'shop', 'username' => 'root', 'password' => 'secret', 'host' => 'db',
            'port' => '3307', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => 'wp_',
        ]);

        contentInvoke($bootstrap, 'setDatabaseConstants');

        expect($this->constants->queued)->toBe([
            'DB_NAME' => 'shop',
            'DB_USER' => 'root',
            'DB_PASSWORD' => 'secret',
            'DB_HOST' => 'db:3307',
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATE' => 'utf8mb4_unicode_ci',
            'DB_PREFIX' => 'wp_',
        ]);
    });

    it('leaves out the database settings that are missing and keeps a host without port', function (): void {
        $bootstrap = contentBootstrap();
        (new ReflectionProperty(Bootstrap::class, 'db'))->setValue($bootstrap, ['database' => 'shop', 'host' => 'db', 'port' => null]);

        contentInvoke($bootstrap, 'setDatabaseConstants');

        expect($this->constants->queued)->toBe(['DB_NAME' => 'shop', 'DB_HOST' => 'db']);
    });

    it('gives the console a host and a protocol so WordPress can build URLs', function (): void {
        $server = $_SERVER;
        config()->set('app.url', 'https://cli.example.test');

        try {
            contentInvoke(contentBootstrap(), 'setConsoleServerVariables');

            $current = $_SERVER;

            expect($current['HTTP_HOST'])->toBe('cli.example.test')
                ->and($current['SERVER_PROTOCOL'])->toBe('https');
        } finally {
            $_SERVER = $server;
        }
    });
});

describe('Bootstrap lightweight requests', function (): void {
    it('treats only /api/ requests of the web as lightweight', function (bool $console, string $uri, bool $expected): void {
        $this->app->instance('request', Request::create($uri));

        expect(contentInvoke(contentBootstrap(console: $console), 'isLightweightRequest'))->toBe($expected);
    })->with([
        'api route' => [false, '/api/posts', true],
        'front page' => [false, '/', false],
        'a path that only contains api' => [false, '/blog/api/posts', false],
        'console even on an api uri' => [true, '/api/posts', false],
    ]);

    it('loads every plugin when the configuration does not restrict them', function (mixed $setting): void {
        config()->set('wordpress.api_plugins', $setting);
        $added = contentRecordFilters();

        contentInvoke(contentBootstrap(), 'applyLightweightFilters');

        expect($added)->toHaveCount(0);
    })->with(['not configured' => [null], 'wildcard' => [['*']], 'wildcard among others' => [['seo', '*']]]);

    it('loads no plugin at all when the configured list is empty', function (): void {
        config()->set('wordpress.api_plugins', []);
        $added = contentRecordFilters();

        contentInvoke(contentBootstrap(), 'applyLightweightFilters');

        expect($added->getArrayCopy())->toBe([['pre_option_active_plugins', '__return_empty_array', 10]]);
    });

    it('keeps only the allowed plugins, by directory, by file or by wildcard', function (): void {
        config()->set('wordpress.api_plugins', ['seo', 'hello.php', 'woocommerce*']);
        DB::shouldReceive('table->where->value')->once()->andReturn(serialize([
            'seo/seo.php',
            'hello.php',
            'woocommerce/woocommerce.php',
            'woocommerce-subscriptions/main.php',
            'gallery/gallery.php',
            'seo-extra/extra.php',
        ]));

        $added = contentRecordFilters();

        contentInvoke(contentBootstrap(), 'applyLightweightFilters');

        expect($added)->toHaveCount(1)
            ->and($added[0][0])->toBe('pre_option_active_plugins')
            ->and(($added[0][1])())->toBe([
                'seo/seo.php',
                'hello.php',
                'woocommerce/woocommerce.php',
                'woocommerce-subscriptions/main.php',
            ]);
    });

    it('allows nothing when the plugin list cannot be read', function (): void {
        DB::shouldReceive('table')->andThrow(new RuntimeException('no database'));

        expect(contentInvoke(contentBootstrap(), 'resolveAllowedPlugins', ['seo']))->toBe([]);
    });

    it('allows nothing when no plugin is active', function (): void {
        DB::shouldReceive('table->where->value')->andReturn(null);

        expect(contentInvoke(contentBootstrap(), 'resolveAllowedPlugins', ['seo']))->toBe([]);
    });
});

describe('Bootstrap error handling', function (): void {
    it('swallows deprecations raised by WordPress and forwards every other error', function (): void {
        $forwarded = [];
        set_error_handler(function (int $level, string $message) use (&$forwarded): bool {
            $forwarded[] = $message;

            return true;
        });

        try {
            contentInvoke(contentBootstrap(), 'withWordPressErrorHandling', function (): void {
                trigger_error('old api', E_USER_DEPRECATED);
                trigger_error('careful', E_USER_WARNING);
            });
        } finally {
            restore_error_handler();
        }

        expect($forwarded)->toBe(['careful']);
    });

    it('puts the previous error handler back once the callback is done, even when it throws', function (): void {
        $previous = static fn (): bool => true;
        set_error_handler($previous);

        try {
            try {
                contentInvoke(contentBootstrap(), 'withWordPressErrorHandling', function (): void {
                    throw new RuntimeException('boom');
                });
            } catch (RuntimeException) {
            }

            $current = set_error_handler(static fn (): bool => true);
            restore_error_handler();
        } finally {
            restore_error_handler();
        }

        expect($current)->toBe($previous);
    });
});

describe('Bootstrap action hooks', function (): void {
    it('rewrites network URLs once WordPress runs its init action under WP-CLI', function (): void {
        $calls = [];
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (...$arguments) use (&$calls, $action) {
            $calls[] = $arguments;

            return $action;
        });
        contentRecordFilters();

        contentInvoke(contentBootstrap(wpCli: true, action: $action), 'setupActionHooks');

        expect($calls)->toHaveCount(1)
            ->and($calls[0][0])->toBe('init')
            ->and($calls[0][2])->toBe(1);
    });

    it('rewrites network URLs straight away outside WP-CLI', function (): void {
        $calls = [];
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (...$arguments) use (&$calls, $action) {
            $calls[] = $arguments;

            return $action;
        });
        contentRecordFilters();

        contentInvoke(contentBootstrap(action: $action), 'setupActionHooks');

        expect($calls)->toHaveCount(1)
            ->and($calls[0][0])->toBe('network_site_url')
            ->and(array_slice($calls[0], 2))->toBe([10, 3]);
    });

    it('leaves alone a template that is not a Blade file', function (): void {
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->with('network_site_url', Mockery::any(), 10, 3);
        $added = contentRecordFilters();

        contentInvoke(contentBootstrap(action: $action), 'setupActionHooks');

        [$hook, $callback, $priority] = $added[0];

        expect($hook)->toBe('template_include')
            ->and($priority)->toBe(PHP_INT_MAX)
            ->and($callback('/theme/page.php'))->toBe('/theme/page.php')
            ->and($callback(false))->toBeFalse();
    });
});

describe('Bootstrap boot', function (): void {
    it('marks WordPress as installing when the console runs before the install', function (): void {
        DB::shouldReceive('getConfig')->andReturn(['driver' => 'sqlite', 'prefix' => '']);
        DB::shouldReceive('connection->getConfig')->andReturn(['driver' => 'sqlite']);
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->with('init', Mockery::type('callable'), 1);

        contentBootstrap(console: true, wpCli: true, action: $action)->boot();

        expect($this->constants->queued)->toMatchArray(['WP_INSTALLING' => true]);
    });

    it('does nothing more than hooking the URLs for a web request on a site that is not installed', function (): void {
        DB::shouldReceive('getConfig')->andReturn(['driver' => 'sqlite', 'prefix' => '']);
        DB::shouldReceive('connection->getConfig')->andReturn(['driver' => 'sqlite']);
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->once()->with('network_site_url', Mockery::type('callable'), 10, 3);

        contentBootstrap(action: $action)->boot();

        expect($this->constants->queued)->not->toHaveKey('WP_INSTALLING');
    });
});
