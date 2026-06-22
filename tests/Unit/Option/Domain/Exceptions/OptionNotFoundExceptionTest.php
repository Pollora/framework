<?php

declare(strict_types=1);

use Pollora\Option\OptionNotFoundException;

describe('OptionNotFoundException', function (): void {
    it('creates exception with formatted message', function (): void {
        $exception = new OptionNotFoundException('test_key');

        expect($exception->getMessage())->toBe("Option 'test_key' not found");
    });

    it('handles special characters in key', function (): void {
        $exception = new OptionNotFoundException('test-key_with.special');

        expect($exception->getMessage())->toBe("Option 'test-key_with.special' not found");
    });
});
