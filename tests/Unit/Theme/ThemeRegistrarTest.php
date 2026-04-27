<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Pollora\Theme\Application\Services\ThemeRegistrar;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;

function setupRegistrarMocks(Application $container, $parser): void
{
    $parser->shouldReceive('parseThemeHeaders')->andReturn(['Name' => 'Test Theme', 'Version' => '1.0.0']);
}

describe('ThemeRegistrar', function (): void {
    beforeEach(function (): void {
        $this->container = new Application;
        $this->parser = Mockery::mock(WordPressThemeParser::class)->shouldIgnoreMissing();
        $this->registrar = new ThemeRegistrar($this->container, $this->parser);
    });

    it('can register active theme', function (): void {
        $this->parser->shouldReceive('parseThemeHeaders')
            ->once()
            ->with('/path/to/theme/style.css')
            ->andReturn(['Name' => 'Test Theme', 'Version' => '1.0.0']);

        $theme = $this->registrar->register();

        expect($theme)->toBeInstanceOf(ThemeModuleInterface::class);
        expect($theme->getName())->toBe('test-theme');
        expect($theme->getPath())->toBe('/path/to/theme');
        expect($theme->isEnabled())->toBeTrue();
    });

    it('can get active theme', function (): void {
        setupRegistrarMocks($this->container, $this->parser);
        $registeredTheme = $this->registrar->register();
        $activeTheme = $this->registrar->getActiveTheme();

        expect($activeTheme)->toBe($registeredTheme);
    });

    it('returns null when no theme registered', function (): void {
        expect($this->registrar->getActiveTheme())->toBeNull();
    });

    it('can check if theme is active', function (): void {
        setupRegistrarMocks($this->container, $this->parser);

        $this->registrar->register();

        expect($this->registrar->isThemeActive('test-theme'))->toBeTrue();
        expect($this->registrar->isThemeActive('TEST-THEME'))->toBeTrue();
        expect($this->registrar->isThemeActive('other-theme'))->toBeFalse();
    });

    it('returns false when no theme registered for isActive check', function (): void {
        expect($this->registrar->isThemeActive('any-theme'))->toBeFalse();
    });

    it('can reset active theme', function (): void {
        setupRegistrarMocks($this->container, $this->parser);

        $this->registrar->register();
        $this->registrar->resetActiveTheme();

        expect($this->registrar->getActiveTheme())->toBeNull();
        expect($this->registrar->isThemeActive('test-theme'))->toBeFalse();
    });

    it('parses theme headers when no data provided', function (): void {
        $parsedData = ['Name' => 'Parsed Theme', 'Version' => '2.0.0'];

        $this->parser->shouldReceive('parseThemeHeaders')
            ->once()
            ->with('/path/to/theme/style.css')
            ->andReturn($parsedData);

        $theme = $this->registrar->register();

        expect($theme->getHeaders())->toBe($parsedData);
    });
});
