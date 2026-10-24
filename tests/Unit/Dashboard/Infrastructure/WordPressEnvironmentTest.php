<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Dashboard\Domain\Contracts\WordPressEnvironmentInterface;
use Pollora\Dashboard\Domain\Support\UnavailableWordPressEnvironment;
use Pollora\Dashboard\Infrastructure\Adapters\WordPressEnvironment;

/**
 * The WordPress side of the dashboard's environment port: the calls the
 * SystemInfoCollector used to make itself, with the same guards.
 *
 * activeTheme() is covered in tests/Feature/Dashboard: defining wp_get_theme()
 * here would leak into unit tests that rely on it being undefined.
 */
describe('WordPressEnvironment', function (): void {
    it('implements the domain port', function (): void {
        expect(new WordPressEnvironment)->toBeInstanceOf(WordPressEnvironmentInterface::class);
    });

    it('reads the locale from get_locale()', function (): void {
        Functions\when('get_locale')->justReturn('fr_FR');

        expect((new WordPressEnvironment)->locale())->toBe('fr_FR');
    });

    it('reads the version from get_bloginfo()', function (): void {
        Functions\expect('get_bloginfo')->once()->with('version')->andReturn('6.8.1');

        expect((new WordPressEnvironment)->version())->toBe('6.8.1');
    });

    it('reads the multisite flag from is_multisite()', function (bool $multisite): void {
        Functions\when('is_multisite')->justReturn($multisite);

        expect((new WordPressEnvironment)->isMultisite())->toBe($multisite);
    })->with([true, false]);

    it('reads the permalink structure from the option', function (): void {
        // The suite stubs get_option() for every test; override the stub
        Functions\when('get_option')->alias(
            fn (string $option, mixed $default = false): mixed => $option === 'permalink_structure' ? '/%postname%/' : $default
        );

        expect((new WordPressEnvironment)->permalinkStructure())->toBe('/%postname%/');
    });

    it('reads a post type label from its registered object', function (): void {
        Functions\when('get_post_type_object')->alias(
            fn (string $slug): ?object => $slug === 'project' ? (object) ['labels' => (object) ['name' => 'Projects']] : null
        );

        $environment = new WordPressEnvironment;

        expect($environment->postTypeLabel('project'))->toBe('Projects')
            ->and($environment->postTypeLabel('unknown'))->toBeNull();
    });

    it('reads a taxonomy label from its registered object', function (): void {
        Functions\when('get_taxonomy')->alias(
            fn (string $slug): object|false => $slug === 'genre' ? (object) ['labels' => (object) ['name' => 'Genres']] : false
        );

        $environment = new WordPressEnvironment;

        expect($environment->taxonomyLabel('genre'))->toBe('Genres')
            ->and($environment->taxonomyLabel('unknown'))->toBeNull();
    });
});

describe('UnavailableWordPressEnvironment', function (): void {
    it('knows nothing', function (): void {
        $environment = new UnavailableWordPressEnvironment;

        expect($environment->locale())->toBeNull()
            ->and($environment->version())->toBeNull()
            ->and($environment->isMultisite())->toBeFalse()
            ->and($environment->permalinkStructure())->toBe('')
            ->and($environment->postTypeLabel('post'))->toBeNull()
            ->and($environment->taxonomyLabel('category'))->toBeNull()
            ->and($environment->activeTheme())->toBeNull();
    });
});
