<?php

declare(strict_types=1);

use Pollora\Theme\Application\Services\ThemeRegistrar;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;

require_once dirname(__DIR__, 2).'/Unit/helpers.php';

beforeEach(function () {
    setupWordPressMocks();

    setWordPressFunction('get_stylesheet', fn () => 'my-theme');
    setWordPressFunction('get_stylesheet_directory', fn () => __DIR__.'/fixtures/my-theme');

    // Create a minimal theme fixture
    $fixturePath = __DIR__.'/fixtures/my-theme';
    if (! is_dir($fixturePath)) {
        mkdir($fixturePath, 0755, true);
        file_put_contents($fixturePath.'/style.css', "/*\nTheme Name: My Theme\nVersion: 1.0.0\n*/\n");
    }
});

afterEach(function () {
    // Clean up fixture
    $fixturePath = __DIR__.'/fixtures/my-theme';
    if (is_dir($fixturePath)) {
        @unlink($fixturePath.'/style.css');
        @rmdir($fixturePath);
        @rmdir(__DIR__.'/fixtures');
    }
});

describe('ThemeRegistrar integration', function () {
    it('registers a theme with parsed headers from style.css', function () {
        $parser = new WordPressThemeParser;
        $registrar = new ThemeRegistrar($this->app, $parser);

        $theme = $registrar->register();

        expect($theme)->toBeInstanceOf(ThemeModuleInterface::class);
        expect($theme->getName())->toBe('my-theme');
        expect($theme->getHeaders())->toHaveKey('Theme Name', 'My Theme');
        expect($theme->getHeaders())->toHaveKey('Version', '1.0.0');
        expect($theme->isEnabled())->toBeTrue();
    });

    it('can retrieve the active theme after registration', function () {
        $parser = new WordPressThemeParser;
        $registrar = new ThemeRegistrar($this->app, $parser);

        $registrar->register();

        expect($registrar->getActiveTheme())->not->toBeNull();
        expect($registrar->isThemeActive('my-theme'))->toBeTrue();
        expect($registrar->isThemeActive('other-theme'))->toBeFalse();
    });

    it('can reset the active theme', function () {
        $parser = new WordPressThemeParser;
        $registrar = new ThemeRegistrar($this->app, $parser);

        $registrar->register();
        $registrar->resetActiveTheme();

        expect($registrar->getActiveTheme())->toBeNull();
        expect($registrar->isThemeActive('my-theme'))->toBeFalse();
    });
});