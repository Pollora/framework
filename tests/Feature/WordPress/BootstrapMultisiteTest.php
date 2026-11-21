<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Http\Request;
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
final class NetworkRecordingConstantManager extends ConstantManager
{
    /** @var array<string, mixed> */
    public array $queued = [];

    public function queue(string $key, mixed $value): void
    {
        $this->queued[$key] = $value;
    }

    public function apply(): void {}
}

function networkBootstrap(bool $console = false): Bootstrap
{
    $detector = Mockery::mock(ConsoleDetectionService::class);
    $detector->shouldReceive('isConsole')->andReturn($console);
    $detector->shouldReceive('isWpCli')->andReturn(false);

    $debugDetector = Mockery::mock(DebugDetectorInterface::class);
    $debugDetector->shouldReceive('isDebugMode')->andReturn(false);

    return new Bootstrap($detector, $debugDetector, Mockery::mock(Action::class));
}

function networkInvoke(Bootstrap $bootstrap, string $method): mixed
{
    return (new ReflectionMethod($bootstrap, $method))->invoke($bootstrap);
}

/**
 * Declare the network the way a project does, in the WordPress constants.
 */
function networkDeclare(bool $network): void
{
    config()->set('wordpress.constants', $network ? ['multisite' => true, 'subdomain_install' => false] : []);
}

beforeEach(function (): void {
    $this->constants = new NetworkRecordingConstantManager;
    Constant::swap($this->constants);
    config()->set('app.url', 'https://multi.test');
});

describe('Bootstrap in a network', function (): void {
    it('leaves the home and site URLs to the database', function (): void {
        networkDeclare(true);

        networkInvoke(networkBootstrap(), 'setLocationConstants');

        expect($this->constants->queued)->not->toHaveKeys(['WP_HOME', 'WP_SITEURL'])
            ->and($this->constants->queued)->toHaveKeys(['WP_PATH', 'ABSPATH', 'WP_CONTENT_DIR', 'WP_CONTENT_URL']);
    });

    it('keeps defining them on a single site', function (): void {
        networkDeclare(false);

        networkInvoke(networkBootstrap(), 'setLocationConstants');

        expect($this->constants->queued)->toMatchArray([
            'WP_HOME' => 'https://multi.test',
            'WP_SITEURL' => 'https://multi.test/cms/',
        ]);
    });

    it('filters the home option of the main site, before WordPress loads', function (): void {
        networkDeclare(true);
        $filters = [];
        Functions\when('add_filter')->alias(function (string $hook, callable $callback) use (&$filters): bool {
            $filters[$hook] = $callback;

            return true;
        });

        networkBootstrap()->register();

        expect($filters)->toHaveKey('option_home');

        $had = array_key_exists('blog_id', $GLOBALS) || array_key_exists('current_site', $GLOBALS);
        $saved = [$GLOBALS['blog_id'] ?? null, $GLOBALS['current_site'] ?? null];

        try {
            $GLOBALS['current_site'] = (object) ['blog_id' => 1];
            $GLOBALS['blog_id'] = 1;
            $main = $filters['option_home']('https://multi.test/cms');

            $GLOBALS['blog_id'] = 2;
            $sub = $filters['option_home']('https://multi.test/cms');
        } finally {
            [$GLOBALS['blog_id'], $GLOBALS['current_site']] = $saved;
            if (! $had) {
                unset($GLOBALS['blog_id'], $GLOBALS['current_site']);
            }
        }

        expect($main)->toBe('https://multi.test')->and($sub)->toBe('https://multi.test/cms');
    });

    it('adds no home filter on a single site', function (): void {
        networkDeclare(false);
        $hooks = [];
        Functions\when('add_filter')->alias(function (string $hook) use (&$hooks): bool {
            $hooks[] = $hook;

            return true;
        });

        networkBootstrap()->register();

        expect($hooks)->not->toContain('option_home');
    });

    it('gives the console the port and the request WordPress reads to find the site', function (): void {
        networkDeclare(true);
        config()->set('app.url', 'http://multi.test:8081/network');
        $server = $_SERVER;
        unset($_SERVER['REQUEST_URI']);

        try {
            networkInvoke(networkBootstrap(console: true), 'setConsoleServerVariables');
            $current = $_SERVER;

            expect($current['HTTP_HOST'])->toBe('multi.test:8081')
                ->and($current['REQUEST_URI'])->toBe('/network/')
                ->and($current['SERVER_PROTOCOL'])->toBe('https');
        } finally {
            $_SERVER = $server;
        }
    });

    it('keeps a request URI the console already has', function (): void {
        networkDeclare(true);
        $server = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/site2/';

        try {
            networkInvoke(networkBootstrap(console: true), 'setConsoleServerVariables');
            $current = $_SERVER;

            expect($current['REQUEST_URI'])->toBe('/site2/');
        } finally {
            $_SERVER = $server;
        }
    });

    it('leaves the console of a single site as it was', function (): void {
        networkDeclare(false);
        config()->set('app.url', 'http://multi.test:8081');
        $server = $_SERVER;
        unset($_SERVER['REQUEST_URI']);

        try {
            networkInvoke(networkBootstrap(console: true), 'setConsoleServerVariables');
            $current = $_SERVER;

            expect($current['HTTP_HOST'])->toBe('multi.test')
                ->and($current)->not->toHaveKey('REQUEST_URI');
        } finally {
            $_SERVER = $server;
        }
    });

    it('sends network URLs to the WordPress directory of the network domain', function (string $url, string $path, string $expected): void {
        if (! class_exists('WP_Network', false)) {
            eval('class WP_Network {}');
        }

        $network = new WP_Network;
        $network->domain = 'multi.test';
        $network->path = '/';

        Functions\when('get_current_site')->justReturn($network);
        Functions\when('set_url_scheme')->returnArg(1);

        expect(networkBootstrap()->rewriteNetworkUrl($url, $path))->toBe($expected);
    })->with([
        'no path' => ['', '', 'https://multi.test/'],
        'network admin' => ['', 'wp-admin/network/', 'https://multi.test/cms/wp-admin/network/'],
        'unsafe characters dropped' => ['', 'wp-login.php?x=<b>', 'https://multi.test/cms/wp-login.phpxb'],
    ]);

    it('does not turn a front request under /cms/ into a theme request', function (): void {
        networkDeclare(true);
        $this->app->instance('request', Request::create('/cms/wp-admin/'));

        networkInvoke(networkBootstrap(), 'defineWordPressConstants');

        expect($this->constants->queued['WP_USE_THEMES'])->toBeFalse()
            ->and($this->constants->queued['MULTISITE'])->toBeTrue();
    });
});
