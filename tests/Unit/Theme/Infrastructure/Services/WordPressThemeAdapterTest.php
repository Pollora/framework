<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Pollora\Theme\Infrastructure\Services\WordPressThemeAdapter;
use Psr\Log\LoggerInterface;

function adapterTempDir(): string
{
    $dir = sys_get_temp_dir().'/pollora-adapter-'.bin2hex(random_bytes(6));
    mkdir($dir);

    return $dir;
}

describe('WordPressThemeAdapter theme directories', function (): void {
    it('refuses an empty path without calling WordPress', function (): void {
        Functions\when('register_theme_directory')->justReturn(true);

        expect((new WordPressThemeAdapter)->registerThemeDirectory(''))->toBeFalse()
            ->and((new WordPressThemeAdapter)->registerThemeDirectory('0'))->toBeFalse();
    });

    it('refuses a directory that does not exist', function (): void {
        Functions\expect('register_theme_directory')->never();

        expect((new WordPressThemeAdapter)->registerThemeDirectory('/does/not/exist-'.bin2hex(random_bytes(4))))->toBeFalse();
    });

    it('registers an existing directory by its resolved real path', function (): void {
        $dir = adapterTempDir();
        $real = realpath($dir);

        try {
            Functions\expect('register_theme_directory')->once()->with($real)->andReturn(true);

            $result = (new WordPressThemeAdapter)->registerThemeDirectory($dir);
        } finally {
            rmdir($dir);
        }

        expect($result)->toBeTrue();
    });

    it('collapses repeated slashes before registering', function (): void {
        $dir = adapterTempDir();
        $real = realpath($dir);

        try {
            Functions\expect('register_theme_directory')->once()->with($real)->andReturn(true);

            $result = (new WordPressThemeAdapter)->registerThemeDirectory($dir.'//');
        } finally {
            rmdir($dir);
        }

        expect($result)->toBeTrue();
    });

    it('returns false and logs when WordPress throws while registering', function (): void {
        $dir = adapterTempDir();
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn ($m): bool => str_contains((string) $m, 'Failed to register theme directory: boom')));
        $previous = Container::getInstance();
        $container = new Container;
        $container->instance(LoggerInterface::class, $logger);
        Container::setInstance($container);

        try {
            Functions\when('register_theme_directory')->alias(function (): never {
                throw new RuntimeException('boom');
            });

            $result = (new WordPressThemeAdapter)->registerThemeDirectory($dir);
        } finally {
            Container::setInstance($previous);
            rmdir($dir);
        }

        expect($result)->toBeFalse();
    });
});

describe('WordPressThemeAdapter stylesheet', function (): void {
    it('returns the active stylesheet', function (): void {
        Functions\when('get_stylesheet')->justReturn('my-theme');

        expect((new WordPressThemeAdapter)->getStylesheet())->toBe('my-theme');
    });

    it('falls back to the default theme name when the stylesheet is empty', function (): void {
        Functions\when('get_stylesheet')->justReturn('');

        expect((new WordPressThemeAdapter)->getStylesheet())->toBe(WordPressThemeAdapter::DEFAULT_THEME_NAME);
    });

    it('falls back to the default theme name when WordPress throws', function (): void {
        Functions\when('get_stylesheet')->alias(function (): never {
            throw new RuntimeException('no theme');
        });

        expect((new WordPressThemeAdapter)->getStylesheet())->toBe('default');
    });

    it('adds a single trailing slash to the stylesheet directory URI', function (): void {
        Functions\when('get_stylesheet_directory_uri')->justReturn('https://example.test/wp-content/themes/demo///');

        expect((new WordPressThemeAdapter)->getStylesheetDirectoryUri())->toBe('https://example.test/wp-content/themes/demo/');
    });

    it('falls back to the root URI when the stylesheet directory URI is empty or throws', function (): void {
        Functions\when('get_stylesheet_directory_uri')->justReturn('');
        expect((new WordPressThemeAdapter)->getStylesheetDirectoryUri())->toBe('/');

        Functions\when('get_stylesheet_directory_uri')->alias(function (): never {
            throw new RuntimeException('x');
        });
        expect((new WordPressThemeAdapter)->getStylesheetDirectoryUri())->toBe(WordPressThemeAdapter::DEFAULT_URI);
    });
});

describe('WordPressThemeAdapter fallback theme object', function (): void {
    it('describes a theme that does not exist, with neutral defaults', function (): void {
        $method = new ReflectionMethod(WordPressThemeAdapter::class, 'createFallbackThemeObject');
        $theme = $method->invoke(new WordPressThemeAdapter);

        expect($theme->exists())->toBeFalse()
            ->and($theme->stylesheet)->toBe('default')
            ->and($theme->template)->toBe('default')
            ->and($theme->get_name())->toBe('Default Theme')
            ->and($theme->get_version())->toBe('1.0.0')
            ->and($theme->get('Author'))->toBe('')
            ->and($theme->get('Author', 'nobody'))->toBe('nobody')
            ->and($theme->get('', 'd'))->toBe('d');
    });
});
