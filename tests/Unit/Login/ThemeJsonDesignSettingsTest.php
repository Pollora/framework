<?php

declare(strict_types=1);

use Pollora\Login\Infrastructure\Repositories\ThemeJsonDesignSettings;

/**
 * Reading theme.json is easy; reading it for a page that carries none of
 * WordPress's preset variables is the part that needed measuring.
 */
describe('ThemeJsonDesignSettings', function (): void {
    it('flattens a preset group into slugs and values', function (): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'color' => ['palette' => ['theme' => [
                ['slug' => 'primary', 'color' => '#ff5334', 'name' => 'Primary'],
                ['slug' => 'accent', 'color' => '#feb017', 'name' => 'Accent'],
            ]]],
        ]);

        expect((new ThemeJsonDesignSettings)->colors())
            ->toBe(['primary' => '#ff5334', 'accent' => '#feb017']);
    });

    it('lets the site editor win over the theme, and the theme over WordPress', function (): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'color' => ['palette' => [
                'default' => [['slug' => 'primary', 'color' => '#000000']],
                'theme' => [['slug' => 'primary', 'color' => '#ff5334']],
                'custom' => [['slug' => 'primary', 'color' => '#123456']],
            ]],
        ]);

        expect((new ThemeJsonDesignSettings)->colors()['primary'])->toBe('#123456');
    });

    it('resolves a preset that points at a variable', function (): void {
        // Measured on apiary, whose Tailwind-built theme.json declares
        // `primary` as a reference to itself. CSS discards that as a cycle
        // even on the front end; the declared fallback is the only colour
        // actually in the data.
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'color' => ['palette' => ['theme' => [
                ['slug' => 'primary', 'color' => 'var(--wp--preset--color--primary,#1f2937)'],
                ['slug' => 'nested', 'color' => 'var(--a, var(--b, #abcdef))'],
            ]]],
        ]);

        expect((new ThemeJsonDesignSettings)->colors())
            ->toBe(['primary' => '#1f2937', 'nested' => '#abcdef']);
    });

    it('drops a reference it cannot resolve, so the role falls to its next slug', function (string $value): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'color' => ['palette' => ['theme' => [['slug' => 'primary', 'color' => $value]]]],
        ]);

        expect((new ThemeJsonDesignSettings)->colors())->toBe([]);
    })->with([
        'no fallback' => ['var(--wp--preset--color--primary)'],
        'empty fallback' => ['var(--x,   )'],
        'only part of the value' => ['linear-gradient(var(--a), red)'],
        'unbalanced' => ['var(--a'],
        'closed early' => ['var(--a)) something'],
        'closes before the value ends' => ['var(--a) b)'],
        'endless' => ['var(--a, var(--b, var(--c, var(--d, var(--e, var(--f, var(--g, var(--h, var(--i))))))))'],
    ]);

    it('reads through a fallback that is itself a function call', function (): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'color' => ['palette' => ['theme' => [
                ['slug' => 'primary', 'color' => 'var(--a, color-mix(in srgb, red 50%, blue))'],
            ]]],
        ]);

        expect((new ThemeJsonDesignSettings)->colors()['primary'])
            ->toBe('color-mix(in srgb, red 50%, blue)');
    });

    it('drops a preset that is only whitespace', function (): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'color' => ['palette' => ['theme' => [['slug' => 'primary', 'color' => '   ']]]],
        ]);

        expect((new ThemeJsonDesignSettings)->colors())->toBe([]);
    });

    it('keeps a value that merely looks like a function', function (): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'color' => ['palette' => ['theme' => [['slug' => 'primary', 'color' => 'oklch(63.7% .237 25.331)']]]],
        ]);

        expect((new ThemeJsonDesignSettings)->colors()['primary'])->toBe('oklch(63.7% .237 25.331)');
    });

    it('skips a preset that is not one', function (): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'color' => ['palette' => ['theme' => [
                'not an array',
                ['color' => '#ff5334'],
                ['slug' => '', 'color' => '#ff5334'],
                ['slug' => 'ok', 'color' => 12],
                ['slug' => 'good', 'color' => '#ff5334'],
            ]]],
        ]);

        expect((new ThemeJsonDesignSettings)->colors())->toBe(['good' => '#ff5334']);
    });

    it('reads radii and fonts from their own keys', function (): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn([
            'border' => ['radiusSizes' => ['theme' => [['slug' => 'lg', 'size' => '.5rem']]]],
            'typography' => ['fontFamilies' => ['theme' => [['slug' => 'sans', 'fontFamily' => 'Inter, sans-serif']]]],
        ]);

        $settings = new ThemeJsonDesignSettings;

        expect($settings->radii())->toBe(['lg' => '.5rem'])
            ->and($settings->fontFamilies())->toBe(['sans' => 'Inter, sans-serif']);
    });

    it('answers nothing rather than fail on a theme that declares nothing', function (mixed $answer): void {
        Brain\Monkey\Functions\when('wp_get_global_settings')->justReturn($answer);

        $settings = new ThemeJsonDesignSettings;

        expect($settings->colors())->toBe([])
            ->and($settings->radii())->toBe([])
            ->and($settings->fontFamilies())->toBe([]);
    })->with([
        'nothing at all' => [[]],
        'not an array' => [null],
        'group missing its origins' => [['color' => ['palette' => 'nope']]],
        'origin is not a list' => [['color' => ['palette' => ['theme' => 'nope']]]],
    ]);

    it('reads the settings once per request', function (): void {
        $calls = 0;
        Brain\Monkey\Functions\when('wp_get_global_settings')->alias(function () use (&$calls): array {
            $calls++;

            return ['color' => ['palette' => ['theme' => [['slug' => 'primary', 'color' => '#ff5334']]]]];
        });

        $settings = new ThemeJsonDesignSettings;
        $settings->colors();
        $settings->radii();
        $settings->fontFamilies();

        expect($calls)->toBe(1);
    });
});
