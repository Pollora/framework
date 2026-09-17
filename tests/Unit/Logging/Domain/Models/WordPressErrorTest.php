<?php

declare(strict_types=1);

use Pollora\Logging\Domain\Models\WordPressError;
use Pollora\Logging\Domain\Models\WordPressErrorType;

describe('WordPressError', function (): void {
    it('creates doing_it_wrong error via factory method', function (): void {

        $error = WordPressError::doingItWrong('my_function', 'Do not call this', '6.0');

        expect($error->type)->toBe(WordPressErrorType::DOING_IT_WRONG);
        expect($error->function)->toBe('my_function');
        expect($error->message)->toBe('Do not call this');
        expect($error->version)->toBe('6.0');
        expect($error->replacement)->toBeNull();
    });

    it('creates deprecated function error via factory method', function (): void {
        $error = WordPressError::deprecatedFunction('old_func', 'new_func', '5.9');

        expect($error->type)->toBe(WordPressErrorType::DEPRECATED_FUNCTION);
        expect($error->function)->toBe('old_func');
        expect($error->replacement)->toBe('new_func');
        expect($error->version)->toBe('5.9');
    });

    it('uses fallback replacement text when empty', function (): void {
        $error = WordPressError::deprecatedFunction('old_func', '', '5.9');

        expect($error->replacement)->toBe('no alternative available');
    });

    it('creates deprecated argument error via factory method', function (): void {

        $error = WordPressError::deprecatedArgument('some_func', 'Arg X is deprecated', '6.1');

        expect($error->type)->toBe(WordPressErrorType::DEPRECATED_ARGUMENT);
        expect($error->function)->toBe('some_func');
        expect($error->message)->toBe('Arg X is deprecated');
    });

    it('returns correct log level based on type', function (): void {
        $doingWrong = WordPressError::doingItWrong('f', 'm', '1.0');
        expect($doingWrong->getLogLevel())->toBe('warning');

        $deprecated = WordPressError::deprecatedFunction('f', 'r', '1.0');
        expect($deprecated->getLogLevel())->toBe('info');
    });

    it('returns formatted log message', function (): void {
        $error = WordPressError::doingItWrong('wp_enqueue_script', 'Wrong usage', '6.0');

        expect($error->getLogMessage())->toBe('WordPress: wp_enqueue_script called incorrectly');
    });

    it('returns log context with all fields', function (): void {

        $error = WordPressError::doingItWrong('func', 'msg', '6.0', ['extra' => 'data']);
        $context = $error->getLogContext();

        expect($context)->toHaveKeys(['type', 'function', 'version', 'message', 'extra']);
        expect($context['type'])->toBe('doing_it_wrong');
        expect($context['function'])->toBe('func');
        expect($context['extra'])->toBe('data');
    });

    it('includes replacement in context when present', function (): void {
        $error = WordPressError::deprecatedFunction('old', 'new', '5.0');
        $context = $error->getLogContext();

        expect($context)->toHaveKey('replacement');
        expect($context['replacement'])->toBe('new');
    });

    it('is readonly', function (): void {
        $reflection = new ReflectionClass(WordPressError::class);

        expect($reflection->isReadOnly())->toBeTrue();
    });
});

describe('WordPressErrorType', function (): void {
    it('has correct log levels', function (): void {
        expect(WordPressErrorType::DOING_IT_WRONG->getLogLevel())->toBe('warning');
        expect(WordPressErrorType::DEPRECATED_FUNCTION->getLogLevel())->toBe('info');
        expect(WordPressErrorType::DEPRECATED_ARGUMENT->getLogLevel())->toBe('info');
    });

    it('generates correct log messages', function (): void {
        expect(WordPressErrorType::DOING_IT_WRONG->getLogMessage('test'))
            ->toBe('WordPress: test called incorrectly');
        expect(WordPressErrorType::DEPRECATED_FUNCTION->getLogMessage('old_fn'))
            ->toBe('WordPress: Deprecated function old_fn used');
        expect(WordPressErrorType::DEPRECATED_ARGUMENT->getLogMessage('some_fn'))
            ->toBe('WordPress: Deprecated argument in some_fn');
    });
});
