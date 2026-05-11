<?php

declare(strict_types=1);

use Pollora\Console\Concerns\PromptsForMissingOption;

describe('PromptsForMissingOption', function (): void {
    beforeEach(function (): void {
        $this->trait = new class
        {
            use PromptsForMissingOption {
                buildValidationClosure as public;
                promptForMissingOptionsUsing as public;
            }

            // Stub parent::interact
            public function interact($input, $output): void {}
        };
    });

    describe('buildValidationClosure', function (): void {
        it('returns null when validation is null', function (): void {
            expect($this->trait->buildValidationClosure(null, 'name'))->toBeNull();
        });

        it('returns the closure as-is when given a Closure', function (): void {
            $closure = fn ($value): ?string => null;

            $result = $this->trait->buildValidationClosure($closure, 'name');

            expect($result)->toBe($closure);
        });

        it('builds required validation from string', function (): void {
            $closure = $this->trait->buildValidationClosure('required', 'name');

            expect($closure)->toBeInstanceOf(Closure::class);
            expect($closure(''))->toBe('The name is required.');
            expect($closure('valid'))->toBeNull();
        });

        it('builds url validation from string', function (): void {
            $closure = $this->trait->buildValidationClosure('url', 'website');

            expect($closure('not-a-url'))->toBe('The website must be a valid URL.');
            expect($closure('https://example.com'))->toBeNull();
            expect($closure(''))->toBeNull(); // empty is OK for url-only
        });

        it('builds combined required|url validation', function (): void {
            $closure = $this->trait->buildValidationClosure('required|url', 'endpoint');

            expect($closure(''))->toBe('The endpoint is required.');
            expect($closure('not-url'))->toBe('The endpoint must be a valid URL.');
            expect($closure('https://api.example.com'))->toBeNull();
        });

        it('returns null for non-string non-closure validation', function (): void {
            expect($this->trait->buildValidationClosure(42, 'field'))->toBeNull();
            expect($this->trait->buildValidationClosure([], 'field'))->toBeNull();
        });
    });

    describe('promptForMissingOptionsUsing', function (): void {
        it('returns empty array by default', function (): void {
            expect($this->trait->promptForMissingOptionsUsing())->toBe([]);
        });
    });
});
