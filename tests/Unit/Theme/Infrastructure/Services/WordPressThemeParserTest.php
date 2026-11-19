<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Theme\Domain\Exceptions\ThemeException;
use Pollora\Theme\Domain\Models\ThemeModule;
use Pollora\Theme\Infrastructure\Models\LaravelThemeModule;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;

function parserThemeDir(string $css): string
{
    $dir = sys_get_temp_dir().'/pollora-parser-'.bin2hex(random_bytes(6));
    mkdir($dir);
    file_put_contents($dir.'/style.css', $css);

    return $dir;
}

function parserRemoveDir(string $dir): void
{
    @unlink($dir.'/style.css');
    @rmdir($dir);
}

describe('WordPressThemeParser', function (): void {
    it('reads the headers of the first comment block of style.css', function (): void {
        $dir = parserThemeDir("/*\nTheme Name: Demo\nVersion: 2.1.0\nTheme URI: https://example.test/a:b\n*/\nbody{}");

        try {
            $headers = (new WordPressThemeParser)->parseThemeHeaders($dir.'/style.css');
        } finally {
            parserRemoveDir($dir);
        }

        expect($headers)->toBe([
            'Theme Name' => 'Demo',
            'Version' => '2.1.0',
            'Theme URI' => 'https://example.test/a:b',
        ]);
    });

    it('strips the leading asterisks of a docblock-style header', function (): void {
        $dir = parserThemeDir("/**\n * Theme Name: Star\n * Author: Me\n */");

        try {
            $headers = (new WordPressThemeParser)->parseThemeHeaders($dir.'/style.css');
        } finally {
            parserRemoveDir($dir);
        }

        expect($headers)->toBe(['Theme Name' => 'Star', 'Author' => 'Me']);
    });

    it('returns no header when style.css has no comment block', function (): void {
        $dir = parserThemeDir('body { color: red; }');

        try {
            $headers = (new WordPressThemeParser)->parseThemeHeaders($dir.'/style.css');
        } finally {
            parserRemoveDir($dir);
        }

        expect($headers)->toBe([]);
    });

    it('throws a ThemeException naming the theme when style.css is missing', function (): void {
        $dir = sys_get_temp_dir().'/pollora-parser-missing';

        (new WordPressThemeParser)->parseThemeHeaders($dir.'/style.css');
    })->throws(ThemeException::class, "Theme 'pollora-parser-missing' is missing required files: style.css");

    it('creates a plain ThemeModule, disabled, when there is no container', function (): void {
        $dir = parserThemeDir("/*\nTheme Name: Plain\nTemplate: parent\n*/");

        try {
            $theme = (new WordPressThemeParser)->createThemeFromDirectory('plain', $dir);
        } finally {
            parserRemoveDir($dir);
        }

        expect($theme)->toBeInstanceOf(ThemeModule::class)
            ->and($theme)->not->toBeInstanceOf(LaravelThemeModule::class)
            ->and($theme->isEnabled())->toBeFalse()
            ->and($theme->getHeaders()['Theme Name'])->toBe('Plain')
            ->and($theme->getParentTheme())->toBe('parent');
    });

    it('creates a LaravelThemeModule when a container is given', function (): void {
        $dir = parserThemeDir("/*\nTheme Name: Laravel\n*/");

        try {
            $theme = (new WordPressThemeParser(new Container))->createThemeFromDirectory('laravel', $dir);
        } finally {
            parserRemoveDir($dir);
        }

        expect($theme)->toBeInstanceOf(LaravelThemeModule::class)
            ->and($theme->isEnabled())->toBeFalse();
    });

    it('describes a theme like wp_get_theme(), with defaults for missing headers', function (): void {
        $dir = parserThemeDir("/*\nTheme Name: Info\nVersion: 3.0\nAuthor URI: https://a.test\n*/");

        try {
            $info = (new WordPressThemeParser)->getThemeInfo($dir);
            $base = basename($dir);
        } finally {
            parserRemoveDir($dir);
        }

        expect($info)->toBe([
            'Name' => 'Info',
            'Description' => '',
            'Author' => '',
            'Version' => '3.0',
            'ThemeURI' => null,
            'AuthorURI' => 'https://a.test',
            'Template' => null,
            'Status' => '',
            'Tags' => '',
            'TextDomain' => $base,
            'DomainPath' => '/languages',
        ]);
    });

    it('falls back to the directory name and version 1.0.0 when headers are empty', function (): void {
        $dir = parserThemeDir('/* nothing here */');

        try {
            $info = (new WordPressThemeParser)->getThemeInfo($dir);
            $base = basename($dir);
        } finally {
            parserRemoveDir($dir);
        }

        expect($info['Name'])->toBe($base)
            ->and($info['Version'])->toBe('1.0.0');
    });

    it('detects a child theme from its Template header', function (): void {
        $parser = new WordPressThemeParser;

        expect($parser->isChildTheme(['Template' => 'parent']))->toBeTrue()
            ->and($parser->isChildTheme(['Template' => '']))->toBeFalse()
            ->and($parser->isChildTheme([]))->toBeFalse()
            ->and($parser->getParentTheme(['Template' => 'parent']))->toBe('parent')
            ->and($parser->getParentTheme([]))->toBeNull();
    });

    it('normalizes known header names and keeps unknown ones untouched', function (): void {
        $normalized = (new WordPressThemeParser)->normalizeHeaders([
            'Theme Name' => 'A',
            'author' => 'B',
            'Theme URI' => 'C',
            'domain path' => '/lang',
            'Requires PHP' => '8.3',
        ]);

        expect($normalized)->toBe([
            'Name' => 'A',
            'Author' => 'B',
            'ThemeURI' => 'C',
            'DomainPath' => '/lang',
            'Requires PHP' => '8.3',
        ]);
    });
});
