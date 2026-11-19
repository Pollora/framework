<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Pollora\Logging\Application\Services\WordPressErrorLoggingService;
use Pollora\Logging\Domain\Contracts\WordPressErrorLoggerInterface;
use Pollora\Logging\Domain\Models\WordPressError;
use Pollora\Logging\Domain\Models\WordPressErrorType;
use Pollora\Logging\Domain\Services\WordPressErrorHandler;

beforeEach(function (): void {
    $this->logged = new ArrayObject;
    $logger = Mockery::mock(WordPressErrorLoggerInterface::class);
    $logger->shouldReceive('logError')->andReturnUsing(function (WordPressError $error): void {
        $this->logged[] = $error;
    });

    $this->local = false;
    $this->app = Mockery::mock(Application::class);
    $this->app->shouldReceive('environment')->andReturnUsing(fn (string $name): bool => $name === 'local' && $this->local);

    $this->request = Request::create('https://example.test/shop?page=2', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.4']);

    $this->service = new WordPressErrorLoggingService(new WordPressErrorHandler($logger), $this->app, $this->request);
});

afterEach(function (): void {
    Mockery::close();
});

it('logs a misused function with the request it happened in', function (): void {
    $this->service->handleDoingItWrong('get_posts', '<b>Wrong</b> usage', '6.1');

    expect($this->logged)->toHaveCount(1)
        ->and($this->logged[0]->type)->toBe(WordPressErrorType::DOING_IT_WRONG)
        ->and($this->logged[0]->function)->toBe('get_posts')
        ->and($this->logged[0]->message)->toBe('Wrong usage')
        ->and($this->logged[0]->version)->toBe('6.1')
        ->and($this->logged[0]->context)->toBe([
            'url' => 'https://example.test/shop?page=2',
            'method' => 'POST',
            'ip' => '198.51.100.4',
        ]);
});

it('logs a deprecated function with its replacement and the request context', function (): void {
    $this->service->handleDeprecatedFunction('old_fn', 'new_fn', '5.0');

    expect($this->logged)->toHaveCount(1)
        ->and($this->logged[0]->type)->toBe(WordPressErrorType::DEPRECATED_FUNCTION)
        ->and($this->logged[0]->function)->toBe('old_fn')
        ->and($this->logged[0]->replacement)->toBe('new_fn')
        ->and($this->logged[0]->version)->toBe('5.0')
        ->and($this->logged[0]->context['url'])->toBe('https://example.test/shop?page=2');
});

it('logs a deprecated argument with its message and the request context', function (): void {
    $this->service->handleDeprecatedArgument('some_fn', 'Argument $x is <i>deprecated</i>', '4.2');

    expect($this->logged)->toHaveCount(1)
        ->and($this->logged[0]->type)->toBe(WordPressErrorType::DEPRECATED_ARGUMENT)
        ->and($this->logged[0]->function)->toBe('some_fn')
        ->and($this->logged[0]->message)->toBe('Argument $x is deprecated')
        ->and($this->logged[0]->version)->toBe('4.2')
        ->and($this->logged[0]->context['method'])->toBe('POST');
});

it('adds no backtrace outside the local environment', function (): void {
    $this->service->handleDoingItWrong('get_posts', 'm', '6.1');

    expect($this->logged[0]->context)->not->toHaveKey('backtrace');
});

it('adds a readable backtrace in the local environment, without the logging classes', function (): void {
    $this->local = true;

    $this->service->handleDoingItWrong('get_posts', 'm', '6.1');

    $backtrace = $this->logged[0]->context['backtrace'];

    expect($backtrace)->not->toBeEmpty()
        ->and(count($backtrace))->toBeLessThanOrEqual(10)
        ->and($backtrace)->each->toMatch('/^#\d+ .+\(\) in .+:\d+$/')
        ->and(implode("\n", $backtrace))->not->toContain('WordPressErrorLoggingService')
        ->and(implode("\n", $backtrace))->not->toContain('WordPressErrorHandler');
});

it('keeps WordPress from also raising a PHP error', function (): void {
    expect($this->service->disableTriggerError())->toBeFalse();
});
