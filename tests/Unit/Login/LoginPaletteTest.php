<?php

declare(strict_types=1);

use Pollora\Login\Domain\Contracts\DesignSettingsRepository;
use Pollora\Login\Domain\Models\LoginPalette;

/**
 * A palette is a vocabulary; the login screen needs roles.
 *
 * Two themes shipped by the project name their presets differently — apiary
 * has `primary-hover`, `subtle` and `outline`, theme-default has
 * `primary-vivid`, `background` and `border` — and a Tailwind-built theme.json
 * buries both under three hundred primitives. These cases pin the rule that
 * lets one stylesheet serve all of them.
 */
function settingsFrom(array $colors = [], array $radii = [], array $fonts = []): DesignSettingsRepository
{
    return new readonly class($colors, $radii, $fonts) implements DesignSettingsRepository
    {
        public function __construct(
            private array $colors,
            private array $radii,
            private array $fonts,
        ) {}

        public function colors(): array
        {
            return $this->colors;
        }

        public function radii(): array
        {
            return $this->radii;
        }

        public function fontFamilies(): array
        {
            return $this->fonts;
        }
    };
}

describe('LoginPalette', function (): void {
    it('takes the first slug a role accepts', function (): void {
        $palette = LoginPalette::resolve(settingsFrom([
            'primary' => '#ff5334',
            'brand' => '#000000',
        ]));

        expect($palette->value('primary'))->toBe('#ff5334');
    });

    it('falls through to the next slug when the first is absent', function (): void {
        // apiary declares no `background`; its `surface-alt` is what keeps the
        // page from being the same colour as the card sitting on it.
        $palette = LoginPalette::resolve(settingsFrom([
            'surface' => '#f9fafb',
            'surface-alt' => '#f3f4f6',
        ]));

        expect($palette->value('background'))->toBe('#f3f4f6')
            ->and($palette->value('surface'))->toBe('#f9fafb');
    });

    it('resolves every role even from an empty theme', function (): void {
        $palette = LoginPalette::resolve(settingsFrom());

        $roles = array_merge(
            array_keys(LoginPalette::COLOR_ROLES),
            array_keys(LoginPalette::RADIUS_ROLES),
            array_keys(LoginPalette::FONT_ROLES),
        );

        foreach ($roles as $role) {
            expect($palette->value($role))->not->toBe('');
        }
    });

    it('styles the two themes the project ships without either configuring anything', function (array $presets, array $expected): void {
        $palette = LoginPalette::resolve(settingsFrom($presets));

        foreach ($expected as $role => $value) {
            expect($palette->value($role))->toBe($value);
        }
    })->with([
        'apiary' => [
            [
                'primary' => '#1f2937',
                'primary-hover' => '#111827',
                'accent' => '#fbbf24',
                'foreground' => '#1f2937',
                'muted' => '#6b7280',
                'surface' => '#f9fafb',
                'surface-alt' => '#f3f4f6',
                'outline' => '#e5e7eb',
            ],
            [
                'primary' => '#1f2937',
                'primary-hover' => '#111827',
                'background' => '#f3f4f6',
                'surface' => '#f9fafb',
                'outline' => '#e5e7eb',
            ],
        ],
        'theme-default' => [
            [
                'primary' => '#ff5334',
                'primary-vivid' => '#fb196d',
                'secondary' => '#ff8c12',
                'accent' => '#feb017',
                'foreground' => '#1d142a',
                'muted' => '#6b7280',
                'surface' => '#fafafa',
                'surface-alt' => '#f5f3ff',
                'background' => '#ffffff',
                'border' => '#e5e7eb',
            ],
            [
                'primary' => '#ff5334',
                // No `primary-hover`: theme-default calls that shade
                // `primary-vivid`, and the button's gradient needs both ends.
                'primary-hover' => '#fb196d',
                'background' => '#ffffff',
                'outline' => '#e5e7eb',
            ],
        ],
    ]);

    it('lets a theme point a role at its own slugs', function (): void {
        $palette = LoginPalette::resolve(
            settingsFrom(['brand-600' => '#123456', 'primary' => '#ff0000']),
            ['primary' => ['brand-600']],
        );

        expect($palette->value('primary'))->toBe('#123456');
    });

    it('lets a theme write in a value no preset holds', function (): void {
        $palette = LoginPalette::resolve(settingsFrom(), ['primary' => 'oklch(70% .2 30)']);

        expect($palette->value('primary'))->toBe('oklch(70% .2 30)');
    });

    it('ignores an override that names nothing usable', function (): void {
        $palette = LoginPalette::resolve(
            settingsFrom(['primary' => '#ff5334']),
            ['primary' => '', 'accent' => [], 'muted' => [42, null]],
        );

        expect($palette->value('primary'))->toBe('#ff5334')
            ->and($palette->value('muted'))->toBe(LoginPalette::FALLBACKS['muted']);
    });

    it('skips a preset that exists but is empty', function (): void {
        $palette = LoginPalette::resolve(settingsFrom(['primary' => '', 'brand' => '#abcdef']));

        expect($palette->value('primary'))->toBe('#abcdef');
    });

    it('prefixes every value it emits', function (): void {
        $variables = LoginPalette::resolve(settingsFrom(['primary' => '#ff5334']))->toCssVariables();

        expect($variables)->toHaveKey(LoginPalette::VARIABLE_PREFIX.'primary')
            ->and($variables[LoginPalette::VARIABLE_PREFIX.'primary'])->toBe('#ff5334');
    });

    it('answers a role it was never given', function (): void {
        // Reached when a filter hands back a palette missing a role: the
        // screen still has to render something for it.
        $palette = LoginPalette::fromValues([]);

        expect($palette->value('primary'))->toBe(LoginPalette::FALLBACKS['primary'])
            ->and($palette->value('no-such-role'))->toBe('');
    });

    it('keeps the roles a caller supplied and fills in the rest', function (): void {
        $palette = LoginPalette::fromValues(['primary' => '#000000']);

        expect($palette->value('primary'))->toBe('#000000')
            ->and($palette->value('accent'))->toBe(LoginPalette::FALLBACKS['accent'])
            ->and($palette->values())->toHaveKey('radius');
    });

    it('reads radii and fonts from their own preset groups', function (): void {
        $palette = LoginPalette::resolve(settingsFrom(
            radii: ['lg' => '.5rem', 'md' => '.375rem'],
            fonts: ['sans' => 'Inter, sans-serif'],
        ));

        expect($palette->value('radius'))->toBe('.5rem')
            ->and($palette->value('radius-sm'))->toBe('.375rem')
            ->and($palette->value('font'))->toBe('Inter, sans-serif');
    });
});
