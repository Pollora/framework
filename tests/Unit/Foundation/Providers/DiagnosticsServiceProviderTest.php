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

if (! function_exists('diagnosticsProvider')) {
    /**
     * A provider whose WordPress answers the test chooses. A flag left out is
     * answered by the provider itself, as outside WordPress.
     *
     * @param  array{debug?: bool, rest?: bool, admin?: bool, ajax?: bool}  $answers
     */
    function diagnosticsProvider(Application $app, array $answers = []): DiagnosticsServiceProvider
    {
        return new class($app, $answers) extends DiagnosticsServiceProvider
        {
            public function __construct($app, private readonly array $answers)
            {
                parent::__construct($app);
            }

            protected function debugging(): bool
            {
                return $this->answers['debug'] ?? false;
            }

            protected function isRestRequest(): bool
            {
                return $this->answers['rest'] ?? parent::isRestRequest();
            }

            protected function isAdminRequest(): bool
            {
                return $this->answers['admin'] ?? parent::isAdminRequest();
            }

            protected function isAjaxRequest(): bool
            {
                return $this->answers['ajax'] ?? parent::isAjaxRequest();
            }
        };
    }
}

if (! function_exists('diagnosticsHooks')) {
    /**
     * Register the provider on a container whose hook registries record what is added.
     *
     * @param  array{debug?: bool, rest?: bool, admin?: bool, ajax?: bool}  $answers
     * @return array{app: Application, hooks: array<string, array{callback: callable, priority: int}>}
     */
    function diagnosticsHooks(array $answers): array
    {
        $_SERVER['HTTP_X_POLLORA_DIAGNOSTICS'] = '1';
        $hooks = [];
        $record = function (object $registry) use (&$hooks): object {
            $registry->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback, int $priority = 10) use (&$hooks, $registry): object {
                $hooks[$hook] = ['callback' => $callback, 'priority' => $priority];

                return $registry;
            });

            return $registry;
        };
        $app = new Application(sys_get_temp_dir());
        $app->instance('events', new Dispatcher($app));
        $app->instance(Action::class, $record(m::mock(Action::class)));
        $app->instance(Filter::class, $record(m::mock(Filter::class)));

        diagnosticsProvider($app, $answers)->register();

        return ['app' => $app, 'hooks' => &$hooks];
    }
}

if (! function_exists('diagnosticsResponse')) {
    /**
     * A REST response that records its headers.
     */
    function diagnosticsResponse(): object
    {
        return new class
        {
            public array $headers = [];

            public function header(string $name, string $value): void
            {
                $this->headers[$name] = $value;
            }
        };
    }
}

if (! function_exists('diagnosticsShutdown')) {
    /**
     * Run the shutdown hook and return what it wrote.
     *
     * @param  array{debug?: bool, rest?: bool, admin?: bool, ajax?: bool}  $answers
     */
    function diagnosticsShutdown(array $answers): string
    {
        $callback = diagnosticsHooks($answers)['hooks']['shutdown']['callback'];

        ob_start();
        $callback();

        return (string) ob_get_clean();
    }
}

beforeEach(function (): void {
    $this->saved = [];

    foreach (['HTTP_X_POLLORA_DIAGNOSTICS', 'REQUEST_TIME_FLOAT'] as $key) {
        $this->saved[$key] = array_key_exists($key, $_SERVER) ? [$_SERVER[$key]] : [];
    }

    $this->savedWpdb = array_key_exists('wpdb', $GLOBALS) ? [$GLOBALS['wpdb']] : [];
    unset($_SERVER['HTTP_X_POLLORA_DIAGNOSTICS'], $GLOBALS['wpdb']);
});

afterEach(function (): void {
    foreach ($this->saved as $key => $was) {
        unset($_SERVER[$key]);

        if ($was !== []) {
            $_SERVER[$key] = $was[0];
        }
    }

    unset($GLOBALS['wpdb']);

    if ($this->savedWpdb !== []) {
        $GLOBALS['wpdb'] = $this->savedWpdb[0];
    }
});

describe('DiagnosticsServiceProvider', function (): void {
    it('registers no hook for an ordinary request, even in debug mode', function (): void {
        $app = new Application(sys_get_temp_dir());
        $app->instance(Action::class, m::mock(Action::class)->shouldNotReceive('add')->getMock());
        $app->instance(Filter::class, m::mock(Filter::class)->shouldNotReceive('add')->getMock());

        diagnosticsProvider($app, ['debug' => true])->register();

        expect($app->make(RequestDiagnostics::class))->toBe($app->make(RequestDiagnostics::class));
    });

    it('hooks the REST header, the boot mark and the HTML comment at register(), last in line', function (): void {
        $result = diagnosticsHooks(['debug' => true]);

        expect(array_keys($result['hooks']))->toEqualCanonicalizing(['wp_loaded', 'rest_post_dispatch', 'shutdown'])
            ->and(array_column($result['hooks'], 'priority'))->each->toBe(PHP_INT_MAX);
    });

    it('notes the boot time when wp_loaded fires', function (): void {
        $result = diagnosticsHooks(['debug' => true]);
        $diagnostics = $result['app']->make(RequestDiagnostics::class);

        expect($diagnostics->report(0, 1.0, 1.0)['boot_ms'])->toBeNull();

        $result['hooks']['wp_loaded']['callback']();

        expect($diagnostics->report(0, 1.0, 1.0)['boot_ms'])->toBeFloat();
    });

    it('counts a Laravel query through the event dispatcher', function (): void {
        $result = diagnosticsHooks(['debug' => true]);
        $connection = m::mock(Connection::class)->shouldIgnoreMissing();

        $result['app']->make('events')->dispatch(new QueryExecuted('select 1', [], 0.1, $connection));

        expect($result['app']->make(RequestDiagnostics::class)->report(0, 1.0, 1.0)['laravel_queries'])->toBe(1);
    });
});

describe('The REST response', function (): void {
    it('carries the report as a header and is handed back', function (): void {
        $filter = diagnosticsHooks(['debug' => true])['hooks']['rest_post_dispatch']['callback'];
        $response = diagnosticsResponse();

        expect($filter($response))->toBe($response)
            ->and(json_decode($response->headers[RequestDiagnostics::HEADER], true))->toHaveKeys(['wordpress_queries', 'laravel_queries', 'boot_ms', 'elapsed_ms']);
    });

    it('carries nothing when WP_DEBUG is off, although the header was asked for', function (): void {
        $filter = diagnosticsHooks(['debug' => false])['hooks']['rest_post_dispatch']['callback'];
        $response = diagnosticsResponse();

        expect($filter($response))->toBe($response)
            ->and($response->headers)->toBe([]);
    });

    it('hands back what is not a response untouched', function (mixed $value): void {
        $filter = diagnosticsHooks(['debug' => true])['hooks']['rest_post_dispatch']['callback'];

        expect($filter($value))->toBe($value);
    })->with([
        'a string' => ['not a response'],
        'an object without a header method' => [new stdClass],
        'null' => [null],
    ]);

    it('counts the queries of WordPress', function (mixed $count, int $expected): void {
        $GLOBALS['wpdb'] = (object) ['num_queries' => $count];
        $filter = diagnosticsHooks(['debug' => true])['hooks']['rest_post_dispatch']['callback'];
        $response = diagnosticsResponse();

        $filter($response);

        expect(json_decode($response->headers[RequestDiagnostics::HEADER], true)['wordpress_queries'])->toBe($expected);
    })->with([
        'an integer' => [7, 7],
        'a numeric string, as a driver may give it' => ['7', 7],
    ]);

    it('counts no query when WordPress has no database object yet', function (): void {
        $filter = diagnosticsHooks(['debug' => true])['hooks']['rest_post_dispatch']['callback'];
        $response = diagnosticsResponse();

        $filter($response);

        expect(json_decode($response->headers[RequestDiagnostics::HEADER], true)['wordpress_queries'])->toBe(0);
    });

    it('measures the elapsed time from the start of the request', function (): void {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 10;
        $filter = diagnosticsHooks(['debug' => true])['hooks']['rest_post_dispatch']['callback'];
        $response = diagnosticsResponse();

        $filter($response);

        expect(json_decode($response->headers[RequestDiagnostics::HEADER], true)['elapsed_ms'])->toBeGreaterThanOrEqual(10000.0);
    });

    it('measures from the registration when the server gave no start', function (): void {
        unset($_SERVER['REQUEST_TIME_FLOAT']);
        $filter = diagnosticsHooks(['debug' => true])['hooks']['rest_post_dispatch']['callback'];
        $response = diagnosticsResponse();

        $filter($response);

        expect(json_decode($response->headers[RequestDiagnostics::HEADER], true)['elapsed_ms'])->toBeLessThan(5000.0);
    });

    it('accepts a start given as a string', function (): void {
        $_SERVER['REQUEST_TIME_FLOAT'] = (string) (microtime(true) - 10);
        $filter = diagnosticsHooks(['debug' => true])['hooks']['rest_post_dispatch']['callback'];
        $response = diagnosticsResponse();

        $filter($response);

        expect(json_decode($response->headers[RequestDiagnostics::HEADER], true)['elapsed_ms'])->toBeGreaterThanOrEqual(10000.0);
    });
});

describe('The HTML comment', function (): void {
    it('closes an HTML page', function (): void {
        expect(diagnosticsShutdown(['debug' => true]))->toContain('<!-- pollora:diagnostics {');
    });

    it('is not written when WP_DEBUG is off', function (): void {
        expect(diagnosticsShutdown(['debug' => false]))->toBe('');
    });

    it('is not written where the answer is not a page', function (array $answers): void {
        expect(diagnosticsShutdown(['debug' => true] + $answers))->toBe('');
    })->with([
        'REST' => [['rest' => true]],
        'the administration' => [['admin' => true]],
        'admin-ajax' => [['ajax' => true]],
    ]);
});
