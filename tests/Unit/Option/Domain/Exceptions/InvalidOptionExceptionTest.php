<?php

declare(strict_types=1);

use Pollora\Option\InvalidOptionException;

describe('InvalidOptionException', function (): void {
    it('creates exception with custom message', function (): void {
        $message = 'Custom error message';
        $exception = new InvalidOptionException($message);

        expect($exception->getMessage())->toBe($message);
    });

    it('handles empty message', function (): void {
        $exception = new InvalidOptionException('');

        expect($exception->getMessage())->toBe('');
    });
});
