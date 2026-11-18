<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Mockery as m;
use Pollora\Foundation\Providers\DiagnosticsServiceProvider;
use Pollora\Foundation\Support\RequestDiagnostics;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;

/**
 * A provider whose WP_DEBUG answer the test chooses.
 */
function diagnosticsProvider(Application $app, bool $debug): DiagnosticsServiceProvider
{
    return new class($app, $debug) extends DiagnosticsServiceProvider
    {
        public function __construct($app, private readonly bool $debug)
        {
            parent::__construct($app);
        }

        protected function debugging(): bool
        {
            return $this->debug;
        }
    };
}

beforeEach(function (): void {
    $this->hadHeader = array_key_exists('HTTP_X_POLLORA_DIAGNOSTICS', $_SERVER);
    $this->previousHeader = $_SERVER['HTTP_X_POLLORA_DIAGNOSTICS'] ?? null;
    unset($_SERVER['HTTP_X_POLLORA_DIAGNOSTICS']);
});

afterEach(function (): void {
    unset($_SERVER['HTTP_X_POLLORA_DIAGNOSTICS']);

    if ($this->hadHeader) {
        $_SERVER['HTTP_X_POLLORA_DIAGNOSTICS'] = $this->previousHeader;
    }
});

describe('DiagnosticsServiceProvider', function (): void {
    it('registers no hook for an ordinary request, even in debug mode', function (): void {
        $app = new Application(sys_get_temp_dir());
        $app->instance(Action::class, m::mock(Action::class)->shouldNotReceive('add')->getMock());
        $app->instance(Filter::class, m::mock(Filter::class)->shouldNotReceive('add')->getMock());

        $provider = diagnosticsProvider($app, true);
        $provider->register();
        $provider->boot();

        expect($app->make(RequestDiagnostics::class))->toBe($app->make(RequestDiagnostics::class));
    });

    it('registers no hook when the header is sent but WP_DEBUG is off', function (): void {
        $_SERVER['HTTP_X_POLLORA_DIAGNOSTICS'] = '1';
        $app = new Application(sys_get_temp_dir());
        $app->instance(Action::class, m::mock(Action::class)->shouldNotReceive('add')->getMock());
        $app->instance(Filter::class, m::mock(Filter::class)->shouldNotReceive('add')->getMock());

        $provider = diagnosticsProvider($app, false);
        $provider->register();
        $provider->boot();
    });

    it('hooks the REST header, the boot mark and the HTML comment when asked in debug mode', function (): void {
        $_SERVER['HTTP_X_POLLORA_DIAGNOSTICS'] = '1';
        $hooks = [];
        $action = m::mock(Action::class);
        $callbacks = [];
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback) use (&$hooks, &$callbacks, $action) {
            $hooks[] = $hook;
            $callbacks[$hook] = $callback;

            return $action;
        });
        $filter = m::mock(Filter::class);
        $filter->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback) use (&$hooks, &$callbacks, $filter) {
            $hooks[] = $hook;
            $callbacks[$hook] = $callback;

            return $filter;
        });
        $app = new Application(sys_get_temp_dir());
        $app->instance('events', new Dispatcher($app));
        $app->instance(Action::class, $action);
        $app->instance(Filter::class, $filter);

        $provider = diagnosticsProvider($app, true);
        $provider->register();
        $provider->boot();

        // wp_loaded is hooked unless did_action() says it already fired.
        expect($hooks)->toContain('rest_post_dispatch', 'shutdown');

        // The REST response gets the report as a header and is handed back.
        $response = new class
        {
            public array $headers = [];

            public function header(string $name, string $value): void
            {
                $this->headers[$name] = $value;
            }
        };

        expect($callbacks['rest_post_dispatch']($response))->toBe($response)
            ->and(json_decode($response->headers[RequestDiagnostics::HEADER], true))->toHaveKeys(['wordpress_queries', 'laravel_queries', 'boot_ms', 'elapsed_ms'])
            ->and($callbacks['rest_post_dispatch']('not a response'))->toBe('not a response');

        // An HTML page gets the same report as a trailing comment.
        ob_start();
        $callbacks['shutdown']();

        expect(ob_get_clean())->toContain('<!-- pollora:diagnostics {');
    });

    it('counts a Laravel query through the event dispatcher', function (): void {
        $_SERVER['HTTP_X_POLLORA_DIAGNOSTICS'] = '1';
        $app = new Application(sys_get_temp_dir());
        $app->instance('events', $events = new Dispatcher($app));
        $app->instance(Action::class, m::mock(Action::class)->shouldIgnoreMissing());
        $app->instance(Filter::class, m::mock(Filter::class)->shouldIgnoreMissing());

        $provider = diagnosticsProvider($app, true);
        $provider->register();
        $provider->boot();

        $events->dispatch(new QueryExecuted('select 1', [], 0.1, m::mock(Connection::class)->shouldIgnoreMissing()));

        expect($app->make(RequestDiagnostics::class)->report(0, 1.0, 1.0)['laravel_queries'])->toBe(1);
    });
});
