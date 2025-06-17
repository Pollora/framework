<?php

declare(strict_types=1);

namespace Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;
use Pollora\Theme\Application\Services\ThemeRegistrar;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;

/**
 * Test suite for the theme self-registration system.
 */
class ThemeRegistrarTest extends TestCase
{
    private ThemeRegistrar $registrar;
    private ContainerInterface $container;
    private WordPressThemeParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ContainerInterface::class);
        $this->parser = $this->createMock(WordPressThemeParser::class);
        
        $this->registrar = new ThemeRegistrar($this->container, $this->parser);
    }

    public function test_can_register_active_theme(): void
    {
        // Arrange
        $themeName = 'test-theme';
        $themePath = '/path/to/theme';
        $themeData = ['Name' => 'Test Theme', 'Version' => '1.0.0'];

        $this->parser->expects($this->never())
            ->method('parseThemeHeaders');

        $this->container->expects($this->exactly(2))
            ->method('has')
            ->with('app')
            ->willReturn(true);

        $mockApp = $this->createMock(\Illuminate\Container\Container::class);
        $this->container->expects($this->exactly(2))
            ->method('get')
            ->with('app')
            ->willReturn($mockApp);

        // Act
        $theme = $this->registrar->registerActiveTheme($themeName, $themePath, $themeData);

        // Assert
        $this->assertInstanceOf(ThemeModuleInterface::class, $theme);
        $this->assertEquals($themeName, $theme->getName());
        $this->assertEquals($themePath, $theme->getPath());
        $this->assertTrue($theme->isEnabled());
    }

    public function test_can_get_active_theme(): void
    {
        // Arrange
        $themeName = 'test-theme';
        $themePath = '/path/to/theme';
        
        $this->setupMocksForRegistration();
        
        // Act
        $registeredTheme = $this->registrar->registerActiveTheme($themeName, $themePath);
        $activeTheme = $this->registrar->getActiveTheme();

        // Assert
        $this->assertSame($registeredTheme, $activeTheme);
    }

    public function test_returns_null_when_no_theme_registered(): void
    {
        // Act
        $activeTheme = $this->registrar->getActiveTheme();

        // Assert
        $this->assertNull($activeTheme);
    }

    public function test_can_check_if_theme_is_active(): void
    {
        // Arrange
        $themeName = 'test-theme';
        $themePath = '/path/to/theme';
        
        $this->setupMocksForRegistration();

        // Act
        $this->registrar->registerActiveTheme($themeName, $themePath);
        
        // Assert
        $this->assertTrue($this->registrar->isThemeActive($themeName));
        $this->assertTrue($this->registrar->isThemeActive('TEST-THEME')); // Case insensitive
        $this->assertFalse($this->registrar->isThemeActive('other-theme'));
    }

    public function test_returns_false_when_no_theme_registered_for_is_active_check(): void
    {
        // Act & Assert
        $this->assertFalse($this->registrar->isThemeActive('any-theme'));
    }

    public function test_can_reset_active_theme(): void
    {
        // Arrange
        $themeName = 'test-theme';
        $themePath = '/path/to/theme';
        
        $this->setupMocksForRegistration();
        
        $this->registrar->registerActiveTheme($themeName, $themePath);
        
        // Act
        $this->registrar->resetActiveTheme();
        
        // Assert
        $this->assertNull($this->registrar->getActiveTheme());
        $this->assertFalse($this->registrar->isThemeActive($themeName));
    }

    public function test_parses_theme_headers_when_no_data_provided(): void
    {
        // Arrange
        $themeName = 'test-theme';
        $themePath = '/path/to/theme';
        $expectedStylePath = $themePath . '/style.css';
        $parsedData = ['Name' => 'Parsed Theme', 'Version' => '2.0.0'];

        $this->parser->expects($this->once())
            ->method('parseThemeHeaders')
            ->with($expectedStylePath)
            ->willReturn($parsedData);

        $this->setupMocksForRegistration();

        // Act
        $theme = $this->registrar->registerActiveTheme($themeName, $themePath);

        // Assert
        $this->assertEquals($parsedData, $theme->getHeaders());
    }

    private function setupMocksForRegistration(): void
    {
        $this->container->expects($this->any())
            ->method('has')
            ->with('app')
            ->willReturn(true);

        $mockApp = $this->createMock(\Illuminate\Container\Container::class);
        $this->container->expects($this->any())
            ->method('get')
            ->with('app')
            ->willReturn($mockApp);
    }
} 