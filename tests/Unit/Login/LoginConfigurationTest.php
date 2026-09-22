<?php

declare(strict_types=1);

use Pollora\Login\Domain\Models\LoginConfiguration;

/**
 * The config file is the one thing a theme author writes by hand, so it has
 * to forgive the shapes they will actually write.
 */
describe('LoginConfiguration', function (): void {
    it('customises nothing when the theme ships no config file', function (): void {
        // The whole opt-in rests on this: upgrading the framework must not
        // restyle the screen people log in through.
        $configuration = LoginConfiguration::fromArray(null);

        expect($configuration->enabled)->toBeFalse()
            ->and($configuration->hasLogo())->toBeFalse()
            ->and($configuration->poweredBy)->toBeFalse();
    });

    it('is on as soon as a config file exists', function (): void {
        expect(LoginConfiguration::fromArray([])->enabled)->toBeTrue();
    });

    it('can be switched off without deleting the file', function (): void {
        expect(LoginConfiguration::fromArray(['enabled' => false])->enabled)->toBeFalse();
    });

    it('reads a logo written the short way', function (): void {
        $configuration = LoginConfiguration::fromArray(['logo' => 'resources/assets/images/logo.svg']);

        expect($configuration->logo)->toBe('resources/assets/images/logo.svg')
            ->and($configuration->logoWidth)->toBeNull();
    });

    it('reads a logo written the long way', function (): void {
        $configuration = LoginConfiguration::fromArray(['logo' => [
            'source' => 'logo.svg',
            'width' => 220,
            'height' => 90,
            'url' => 'https://example.test/',
            'text' => 'Example',
        ]]);

        expect($configuration->logo)->toBe('logo.svg')
            ->and($configuration->logoWidth)->toBe(220)
            ->and($configuration->logoHeight)->toBe(90)
            ->and($configuration->url)->toBe('https://example.test/')
            ->and($configuration->text)->toBe('Example');
    });

    it('reads an attachment id whether it was written as a number or a string', function (int|string $source): void {
        expect(LoginConfiguration::fromArray(['logo' => $source])->logo)->toBe(42);
    })->with([42, '42']);

    it('treats an unusable logo as no logo', function (mixed $source): void {
        expect(LoginConfiguration::fromArray(['logo' => $source])->hasLogo())->toBeFalse();
    })->with([
        'empty string' => [''],
        'whitespace' => ['   '],
        'zero' => [0],
        'zero as a string' => ['0'],
        'null' => [null],
        'an array' => [[[]]],
    ]);

    it('drops a size that is not a size', function (mixed $width): void {
        expect(LoginConfiguration::fromArray(['logo' => ['source' => 'a.svg', 'width' => $width]])->logoWidth)
            ->toBeNull();
    })->with([
        'negative' => [-10],
        'zero' => [0],
        'words' => ['wide'],
        'null' => [null],
    ]);

    it('accepts a size written as a numeric string', function (): void {
        expect(LoginConfiguration::fromArray(['logo' => ['source' => 'a.svg', 'width' => '220']])->logoWidth)
            ->toBe(220);
    });

    it('trims the text it is given', function (): void {
        $configuration = LoginConfiguration::fromArray(['logo' => ['source' => 'a.svg', 'text' => '  Example  ']]);

        expect($configuration->text)->toBe('Example');
    });

    it('shows the credit unless asked not to', function (): void {
        expect(LoginConfiguration::fromArray([])->poweredBy)->toBeTrue()
            ->and(LoginConfiguration::fromArray(['powered_by' => false])->poweredBy)->toBeFalse();
    });

    it('carries role overrides through, and ignores a malformed one', function (): void {
        expect(LoginConfiguration::fromArray(['tokens' => ['primary' => ['brand']]])->tokens)
            ->toBe(['primary' => ['brand']])
            ->and(LoginConfiguration::fromArray(['tokens' => 'nope'])->tokens)->toBe([]);
    });
});
