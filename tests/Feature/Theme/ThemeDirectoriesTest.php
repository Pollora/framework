<?php

declare(strict_types=1);

use Pollora\Theme\Infrastructure\Providers\ThemeServiceProvider;

/**
 * Registering Pollora's themes directory must leave WordPress's own in place.
 *
 * get_raw_theme_root() returns a hardcoded '/themes' whenever a single theme
 * directory is registered, and wp_get_theme() resolves that against
 * WP_CONTENT_DIR — outside Pollora's themes directory. The admin then reports
 * the active theme as missing while the front end renders it, because
 * get_stylesheet_directory() goes through the theme_root filter and
 * wp_get_theme() does not.
 */
// WordPress is not loaded here, so stand in for its content directory. The
// themes subdirectory has to exist: only a real one is worth registering.
if (! defined('WP_CONTENT_DIR')) {
    $contentDir = sys_get_temp_dir().'/pollora-content-'.uniqid();
    mkdir($contentDir.'/themes', 0755, true);
    define('WP_CONTENT_DIR', $contentDir);
}

function registerThemeDirectory(string $path): array
{
    $provider = (new ReflectionClass(ThemeServiceProvider::class))->newInstanceWithoutConstructor();

    $method = new ReflectionMethod($provider, 'addToGlobalThemeDirectories');
    $method->invoke($provider, $path);

    return $GLOBALS['wp_theme_directories'];
}

beforeEach(function (): void {
    $this->previousDirectories = $GLOBALS['wp_theme_directories'] ?? null;
    unset($GLOBALS['wp_theme_directories']);
});

afterEach(function (): void {
    if ($this->previousDirectories === null) {
        unset($GLOBALS['wp_theme_directories']);

        return;
    }

    $GLOBALS['wp_theme_directories'] = $this->previousDirectories;
});

describe('ThemeServiceProvider theme directories', function (): void {
    it('keeps WordPress own themes directory alongside the Pollora one', function (): void {
        $directories = registerThemeDirectory('/srv/app/themes');

        expect($directories)->toContain('/srv/app/themes')
            ->and($directories)->toContain(WP_CONTENT_DIR.'/themes')
            ->and($directories)->toHaveCount(2);
    });

    it('never drops directories another caller already registered', function (): void {
        $GLOBALS['wp_theme_directories'] = ['/srv/app/other-themes'];

        expect(registerThemeDirectory('/srv/app/themes'))
            ->toContain('/srv/app/other-themes')
            ->toContain('/srv/app/themes');
    });

    it('does not register the same directory twice', function (): void {
        $GLOBALS['wp_theme_directories'] = ['/srv/app/themes'];

        $directories = registerThemeDirectory('/srv/app/themes');

        expect(array_count_values($directories)['/srv/app/themes'])->toBe(1);
    });

    it('recovers when the global holds something other than an array', function (): void {
        $GLOBALS['wp_theme_directories'] = 'not an array';

        expect(registerThemeDirectory('/srv/app/themes'))->toContain('/srv/app/themes');
    });
});
