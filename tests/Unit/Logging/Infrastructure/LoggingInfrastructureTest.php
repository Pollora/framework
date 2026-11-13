<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\Logging\Application\Services\WordPressErrorLoggingService;
use Pollora\Logging\Domain\Contracts\WordPressErrorHookRegistrarInterface;
use Pollora\Logging\Domain\Contracts\WordPressErrorLoggerInterface;
use Pollora\Logging\Domain\Models\WordPressError;
use Pollora\Logging\Domain\Services\WordPressErrorHandler;
use Pollora\Logging\Infrastructure\Adapters\LaravelWordPressErrorLogger;
use Pollora\Logging\Infrastructure\Providers\LoggingServiceProvider;
use Pollora\Logging\Infrastructure\Services\WordPressErrorHookRegistrar;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
});

afterEach(function (): void {
    Mockery::close();
    Container::setInstance($this->previousContainer);
});

describe('LaravelWordPressErrorLogger', function (): void {
    it('writes the error to the wordpress channel with its level, message and context', function (): void {
        $channel = Mockery::mock(LoggerInterface::class);
        $channel->shouldReceive('log')->once()->with(
            'warning',
            'WordPress: get_posts called incorrectly',
            ['type' => 'doing_it_wrong', 'function' => 'get_posts', 'version' => '6.1', 'message' => 'Bad', 'url' => '/x'],
        );
        $manager = Mockery::mock(LogManager::class);
        $manager->shouldReceive('channel')->with('wordpress')->once()->andReturn($channel);

        (new LaravelWordPressErrorLogger($manager))->logError(WordPressError::doingItWrong('get_posts', 'Bad', '6.1', ['url' => '/x']));
    });

    it('logs deprecations at info level', function (): void {
        $channel = Mockery::mock(LoggerInterface::class);
        $channel->shouldReceive('log')->once()->with(
            'info',
            'WordPress: Deprecated function old_fn used',
            ['type' => 'deprecated_function', 'function' => 'old_fn', 'version' => '5.0', 'message' => '', 'replacement' => 'new_fn'],
        );
        $manager = Mockery::mock(LogManager::class);
        $manager->shouldReceive('channel')->with('wordpress')->andReturn($channel);

        (new LaravelWordPressErrorLogger($manager))->logError(WordPressError::deprecatedFunction('old_fn', 'new_fn', '5.0'));
    });
});

describe('WordPressErrorHookRegistrar', function (): void {
    beforeEach(function (): void {
        $this->actions = new ArrayObject;
        $this->filters = new ArrayObject;

        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback, int $priority = 10, int $args = 1) {
            $this->actions[$hook] = ['callback' => $callback, 'priority' => $priority, 'args' => $args];

            return Mockery::mock(HookInterface::class);
        });
        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback, int $priority = 10, int $args = 1) {
            $this->filters[$hook] = ['callback' => $callback, 'priority' => $priority, 'args' => $args];

            return Mockery::mock(HookInterface::class);
        });

        $this->registrar = new WordPressErrorHookRegistrar($action, $filter);
        $this->registrar->registerErrorHandlers();

        $this->app = new Application(sys_get_temp_dir());
    });

    it('registers the three error actions and the three trigger-error filters', function (): void {
        expect($this->actions->getArrayCopy())->toHaveKeys(['doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run'])
            ->and($this->actions)->toHaveCount(3)
            ->and($this->filters->getArrayCopy())->toHaveKeys(['doing_it_wrong_trigger_error', 'deprecated_function_trigger_error', 'deprecated_argument_trigger_error'])
            ->and($this->filters)->toHaveCount(3)
            ->and(collect($this->actions)->every(fn (array $hook): bool => $hook['priority'] === 10 && $hook['args'] === 3))->toBeTrue()
            ->and(collect($this->filters)->every(fn (array $hook): bool => $hook['priority'] === PHP_INT_MAX && $hook['args'] === 4))->toBeTrue();
    });

    it('hands each WordPress notice to the matching logging service method', function (): void {
        $service = Mockery::mock(WordPressErrorLoggingService::class);
        $service->shouldReceive('handleDoingItWrong')->once()->with('fn_a', 'message a', '6.0');
        $service->shouldReceive('handleDeprecatedFunction')->once()->with('fn_b', 'replacement b', '5.0');
        $service->shouldReceive('handleDeprecatedArgument')->once()->with('fn_c', 'message c', '4.0');
        $this->app->instance(WordPressErrorLoggingService::class, $service);

        $this->actions['doing_it_wrong_run']['callback']('fn_a', 'message a', '6.0');
        $this->actions['deprecated_function_run']['callback']('fn_b', 'replacement b', '5.0');
        $this->actions['deprecated_argument_run']['callback']('fn_c', 'message c', '4.0');
    });

    it('turns off the PHP error WordPress would trigger for each notice', function (string $filter): void {
        $service = Mockery::mock(WordPressErrorLoggingService::class);
        $service->shouldReceive('disableTriggerError')->once()->andReturn(false);
        $this->app->instance(WordPressErrorLoggingService::class, $service);

        expect($this->filters[$filter]['callback'](true, 'fn', 'msg', '6.0'))->toBeFalse();
    })->with(['doing_it_wrong_trigger_error', 'deprecated_function_trigger_error', 'deprecated_argument_trigger_error']);

    it('stays silent when the logging service cannot be resolved', function (): void {
        $this->app->bind(WordPressErrorLoggingService::class, fn (): never => throw new RuntimeException('no service'));

        $this->actions['doing_it_wrong_run']['callback']('fn_a', 'message a', '6.0');
        $this->actions['deprecated_function_run']['callback']('fn_b', 'replacement b', '5.0');
        $this->actions['deprecated_argument_run']['callback']('fn_c', 'message c', '4.0');

        expect($this->filters['doing_it_wrong_trigger_error']['callback'](true, 'fn', 'msg', '6.0'))->toBeTrue();
    });
});

describe('LoggingServiceProvider', function (): void {
    beforeEach(function (): void {
        $this->app = new Application(sys_get_temp_dir());
        $this->app->useStoragePath('/srv/storage');
        $this->app->instance('config', new Repository);

        $this->provider = new LoggingServiceProvider($this->app);
    });

    it('defines a single-file wordpress log channel with its defaults', function (): void {
        $this->provider->register();

        expect(config('logging.channels.wordpress'))->toBe([
            'driver' => 'single',
            'path' => '/srv/storage/logs/wordpress.log',
            'level' => 'debug',
            'days' => 7,
            'replace_placeholders' => true,
        ]);
    });

    it('keeps the channels the application already defined, including its own wordpress channel', function (): void {
        config(['logging.channels' => [
            'stack' => ['driver' => 'stack'],
            'wordpress' => ['driver' => 'daily', 'path' => '/custom.log'],
        ]]);

        $this->provider->register();

        expect(config('logging.channels.stack'))->toBe(['driver' => 'stack'])
            ->and(config('logging.channels.wordpress'))->toBe(['driver' => 'daily', 'path' => '/custom.log']);
    });

    it('binds the logger and the hook registrar interfaces to their implementations', function (): void {
        $this->provider->register();
        $this->app->instance(LogManager::class, Mockery::mock(LogManager::class, ['channel' => Mockery::mock(LoggerInterface::class)]));
        $this->app->instance(Action::class, Mockery::mock(Action::class));
        $this->app->instance(Filter::class, Mockery::mock(Filter::class));

        expect($this->app->make(WordPressErrorLoggerInterface::class))->toBeInstanceOf(LaravelWordPressErrorLogger::class)
            ->and($this->app->make(WordPressErrorHookRegistrarInterface::class))->toBeInstanceOf(WordPressErrorHookRegistrar::class);
    });

    it('shares one error handler and one logging service', function (): void {
        $this->provider->register();
        $this->app->instance(WordPressErrorLoggerInterface::class, Mockery::mock(WordPressErrorLoggerInterface::class));
        $this->app->instance(Request::class, Request::create('/'));

        expect($this->app->make(WordPressErrorHandler::class))->toBe($this->app->make(WordPressErrorHandler::class))
            ->and($this->app->make(WordPressErrorLoggingService::class))->toBe($this->app->make(WordPressErrorLoggingService::class));
    });

    it('registers the WordPress error handlers on boot', function (): void {
        $registrar = Mockery::mock(WordPressErrorHookRegistrarInterface::class);
        $registrar->shouldReceive('registerErrorHandlers')->once();
        $this->app->instance(WordPressErrorHookRegistrarInterface::class, $registrar);

        $this->provider->boot();
    });
});
