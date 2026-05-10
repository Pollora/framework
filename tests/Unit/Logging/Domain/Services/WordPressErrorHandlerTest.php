<?php

declare(strict_types=1);

use Pollora\Logging\Domain\Contracts\WordPressErrorLoggerInterface;
use Pollora\Logging\Domain\Models\WordPressError;
use Pollora\Logging\Domain\Models\WordPressErrorType;
use Pollora\Logging\Domain\Services\WordPressErrorHandler;

beforeEach(function (): void {
    $this->logger = Mockery::mock(WordPressErrorLoggerInterface::class);
    $this->handler = new WordPressErrorHandler($this->logger);
});

describe('WordPressErrorHandler', function (): void {
    it('handles doing_it_wrong by logging error', function (): void {
        $this->logger->shouldReceive('logError')
            ->once()
            ->with(Mockery::on(fn (WordPressError $e) => $e->type === WordPressErrorType::DOING_IT_WRONG
                && $e->function === 'bad_function'
                && $e->message === 'Wrong usage'
            ));

        $this->handler->handleDoingItWrong('bad_function', 'Wrong usage', '6.0');
    });

    it('handles deprecated function by logging error', function (): void {
        $this->logger->shouldReceive('logError')
            ->once()
            ->with(Mockery::on(fn (WordPressError $e) => $e->type === WordPressErrorType::DEPRECATED_FUNCTION
                && $e->function === 'old_func'
                && $e->replacement === 'new_func'
            ));

        $this->handler->handleDeprecatedFunction('old_func', 'new_func', '5.9');
    });

    it('handles deprecated argument by logging error', function (): void {
        $this->logger->shouldReceive('logError')
            ->once()
            ->with(Mockery::on(fn (WordPressError $e) => $e->type === WordPressErrorType::DEPRECATED_ARGUMENT
                && $e->function === 'some_func'
            ));

        $this->handler->handleDeprecatedArgument('some_func', 'Deprecated arg', '6.1');
    });

    it('passes extra context to error', function (): void {
        $this->logger->shouldReceive('logError')
            ->once()
            ->with(Mockery::on(fn (WordPressError $e) => isset($e->context['trace']) && $e->context['trace'] === 'stack'
            ));

        $this->handler->handleDoingItWrong('f', 'msg', '1.0', ['trace' => 'stack']);
    });

    it('disables trigger_error by returning false', function (): void {
        expect($this->handler->disableTriggerError())->toBeFalse();
    });
});
