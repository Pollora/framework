<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Services\Translater;

/**
 * Translater looks every value up as `{$domain}.{$value}` and strips the prefix
 * back off when nothing answered, so a plain config array can be translated
 * through a group file without the config itself calling `__()`.
 *
 * `__()` is reached as a global function, so these tests drive it through the
 * container translator it delegates to rather than by redefining it — the
 * helper is installed by Composer's autoload.files, before Patchwork can
 * instrument it. What is under test is Translater's own traversal, prefix
 * handling and type handling; which catalogue `__()` ends up consulting is
 * pollora/helper-overrider's concern and is covered there.
 */

/**
 * Bind a translator that answers only the given keys, echoing anything else back.
 *
 * @param  array<string, string>  $lines
 */
function bindGroupTranslator(array $lines = []): void
{
    Container::getInstance()->instance('translator', new readonly class($lines)
    {
        /**
         * @param  array<string, string>  $lines
         */
        public function __construct(private array $lines) {}

        public function get(string $key, mixed $replace = [], ?string $locale = null): string
        {
            return $this->lines[$key] ?? $key;
        }

        public function choice(string $key, int $number, mixed $replace = [], ?string $locale = null): string
        {
            return $key;
        }

        public function getLocale(): string
        {
            return 'en';
        }

        public function setLocale(string $locale): void {}
    });
}

describe('wildcard translation', function (): void {
    it('translates every value in a flat array', function (): void {
        bindGroupTranslator([
            'menus.Header menu' => 'Menu en-tête',
            'menus.Footer menu' => 'Menu pied de page',
        ]);

        $result = (new Translater([
            'menu-header' => 'Header menu',
            'menu-footer' => 'Footer menu',
        ], 'menus'))->translate(['*']);

        expect($result)->toBe([
            'menu-header' => 'Menu en-tête',
            'menu-footer' => 'Menu pied de page',
        ]);
    });

    it('returns the bare value when the group holds no entry for it', function (): void {
        bindGroupTranslator();

        $result = (new Translater(['menu-header' => 'Header menu'], 'menus'))->translate(['*']);

        expect($result)->toBe(['menu-header' => 'Header menu']);
    });

    it('recurses into nested arrays', function (): void {
        bindGroupTranslator(['menus.Header menu' => 'Menu en-tête']);

        $result = (new Translater([
            'primary' => ['label' => 'Header menu'],
        ], 'menus'))->translate(['*']);

        expect($result)->toBe(['primary' => ['label' => 'Menu en-tête']]);
    });
});

describe('targeted keys', function (): void {
    it('translates only the named key', function (): void {
        bindGroupTranslator([
            'menus.Header menu' => 'Menu en-tête',
            'menus.Footer menu' => 'Menu pied de page',
        ]);

        $result = (new Translater([
            'menu-header' => 'Header menu',
            'menu-footer' => 'Footer menu',
        ], 'menus'))->translate(['menu-header']);

        expect($result)->toBe([
            'menu-header' => 'Menu en-tête',
            'menu-footer' => 'Footer menu',
        ]);
    });

    it('leaves the array untouched for a key that is not there', function (): void {
        bindGroupTranslator(['menus.Header menu' => 'Menu en-tête']);

        $result = (new Translater(['menu-header' => 'Header menu'], 'menus'))->translate(['absent']);

        expect($result)->toBe(['menu-header' => 'Header menu']);
    });

    // The shape Sidebar uses: translate the name and description of every
    // sidebar, leaving markup keys such as before_widget alone.
    it('translates nested keys through a wildcard segment', function (): void {
        bindGroupTranslator([
            'sidebars.Footer form' => 'Formulaire pied de page',
            'sidebars.Shown in the footer' => 'Affiché en pied de page',
        ]);

        $result = (new Translater([
            ['name' => 'Footer form', 'description' => 'Shown in the footer', 'before_widget' => '<div>'],
        ], 'sidebars'))->translate(['*.name', '*.description']);

        expect($result)->toBe([
            ['name' => 'Formulaire pied de page', 'description' => 'Affiché en pied de page', 'before_widget' => '<div>'],
        ]);
    });

    it('walks a dot-notation path to a single nested value', function (): void {
        bindGroupTranslator(['menus.Header menu' => 'Menu en-tête']);

        $result = (new Translater([
            'primary' => ['label' => 'Header menu', 'slug' => 'Header menu'],
        ], 'menus'))->translate(['primary.label']);

        expect($result)->toBe([
            'primary' => ['label' => 'Menu en-tête', 'slug' => 'Header menu'],
        ]);
    });
});

describe('prefix handling', function (): void {
    // Regression: the prefix used to be removed with an unanchored
    // str_replace(), so a value containing "menus." anywhere lost it —
    // 'Go to menus.example' came back as 'Go to example'.
    it('strips the prefix only at the start, never mid-string', function (): void {
        bindGroupTranslator();

        $result = (new Translater(['a' => 'Go to menus.example'], 'menus'))->translate(['*']);

        expect($result)->toBe(['a' => 'Go to menus.example']);
    });

    it('leaves a translated line alone even when it contains the prefix', function (): void {
        bindGroupTranslator(['menus.Header' => 'Voir menus.example']);

        $result = (new Translater(['a' => 'Header'], 'menus'))->translate(['*']);

        expect($result)->toBe(['a' => 'Voir menus.example']);
    });

    it('uses the domain it was given to build the lookup', function (): void {
        bindGroupTranslator(['sidebars.Header' => 'Depuis sidebars']);

        expect((new Translater(['a' => 'Header'], 'sidebars'))->translate(['*']))
            ->toBe(['a' => 'Depuis sidebars'])
            ->and((new Translater(['a' => 'Header'], 'menus'))->translate(['*']))
            ->toBe(['a' => 'Header']);
    });
});

// Regression: translateItem() was typed string under declare(strict_types=1),
// so a wildcard pass over a config array holding anything else raised a
// TypeError. Sidebar config legitimately carries booleans and integers.
describe('non-string values', function (): void {
    it('passes ints, bools and null through untouched', function (): void {
        bindGroupTranslator(['menus.Header menu' => 'Menu en-tête']);

        $result = (new Translater([
            'label' => 'Header menu',
            'depth' => 3,
            'enabled' => true,
            'parent' => null,
        ], 'menus'))->translate(['*']);

        expect($result)->toBe([
            'label' => 'Menu en-tête',
            'depth' => 3,
            'enabled' => true,
            'parent' => null,
        ]);
    });

    it('passes a non-string through a targeted key too', function (): void {
        bindGroupTranslator();

        $result = (new Translater(['depth' => 3], 'menus'))->translate(['depth']);

        expect($result)->toBe(['depth' => 3]);
    });

    it('passes a non-string through a nested wildcard too', function (): void {
        bindGroupTranslator();

        $result = (new Translater([['name' => 42]], 'sidebars'))->translate(['*.name']);

        expect($result)->toBe([['name' => 42]]);
    });
});

it('returns an empty array unchanged', function (): void {
    bindGroupTranslator();

    expect((new Translater([], 'menus'))->translate(['*']))->toBe([]);
});
