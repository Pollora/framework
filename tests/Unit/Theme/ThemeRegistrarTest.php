<?php

declare(strict_types=1);

namespace Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;
use Pollora\Theme\Application\Services\ThemeRegistrar;
use Psr\Container\ContainerInterface;
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
        $this->parser->expects($this->once())
            ->method('parseThemeHeaders')
            ->with('/path/to/theme/style.css')
            ->willReturn(['Name' => 'Test Theme', 'Version' => '1.0.0']);

        $this->container->expects($this->any())
            ->method('has')
            ->willReturnMap([
                ['app', true],
                [\Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface::class, false],
                [\Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface::class, false],
                [\Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader::class, false],
                [\Pollora\Modules\Infrastructure\Services\ModuleComponentManager::class, false],
                [\Pollora\Modules\Infrastructure\Services\ModuleAssetManager::class, false],
            ]);

        $mockApp = $this->createMock(\Illuminate\Container\Container::class);
        $this->container->expects($this->any())
            ->method('get')
            ->with('app')
            ->willReturn($mockApp);

        // Act
        $theme = $this->registrar->register();

        // Assert
        $this->assertInstanceOf(ThemeModuleInterface::class, $theme);
        $this->assertEquals('test-theme', $theme->getName());
        $this->assertEquals('/path/to/theme', $theme->getPath());
        $this->assertTrue($theme->isEnabled());
    }

    public function test_can_get_active_theme(): void
    {
        // Arrange
        $this->setupMocksForRegistration();

        // Act
        $registeredTheme = $this->registrar->register();
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
        $this->setupMocksForRegistration();

        // Act
        $this->registrar->register();

        // Assert
        $this->assertTrue($this->registrar->isThemeActive('test-theme'));
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
        $this->setupMocksForRegistration();

        $this->registrar->register();

        // Act
        $this->registrar->resetActiveTheme();

        // Assert
        $this->assertNull($this->registrar->getActiveTheme());
        $this->assertFalse($this->registrar->isThemeActive('test-theme'));
    }

    public function test_parses_theme_headers_when_no_data_provided(): void
    {
        // Arrange
        $expectedStylePath = '/path/to/theme/style.css';
        $parsedData = ['Name' => 'Parsed Theme', 'Version' => '2.0.0'];

        $this->parser->expects($this->once())
            ->method('parseThemeHeaders')
            ->with($expectedStylePath)
            ->willReturn($parsedData);

        $this->setupMocksForRegistration();

        // Act
        $theme = $this->registrar->register();

        // Assert
        $this->assertEquals($parsedData, $theme->getHeaders());
    }

    private function setupMocksForRegistration(): void
    {
        $this->parser->expects($this->any())
            ->method('parseThemeHeaders')
            ->willReturn(['Name' => 'Test Theme', 'Version' => '1.0.0']);

        $this->container->expects($this->any())
            ->method('has')
            ->willReturnMap([
                ['app', true],
                [\Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface::class, false],
                [\Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface::class, false],
                [\Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader::class, false],
                [\Pollora\Modules\Infrastructure\Services\ModuleComponentManager::class, false],
                [\Pollora\Modules\Infrastructure\Services\ModuleAssetManager::class, false],
            ]);

        $mockApp = $this->createMock(\Illuminate\Container\Container::class);
        $this->container->expects($this->any())
            ->method('get')
            ->with('app')
            ->willReturn($mockApp);
    }
}
