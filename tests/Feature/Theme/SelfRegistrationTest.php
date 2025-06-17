<?php

declare(strict_types=1);

namespace Tests\Feature\Theme;

use PHPUnit\Framework\TestCase;
use Pollora\Theme\Application\Services\ThemeRegistrar;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;

/**
 * Integration test for the theme self-registration system.
 */
class SelfRegistrationTest extends TestCase
{
    private ThemeRegistrarInterface $registrar;
    private string $testThemePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock container
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')->willReturn(false);

        // Create theme parser
        $parser = new WordPressThemeParser();

        // Create registrar
        $this->registrar = new ThemeRegistrar($container, $parser);

        // Create temporary theme directory for testing
        $this->testThemePath = sys_get_temp_dir() . '/test-theme-' . uniqid();
        mkdir($this->testThemePath, 0755, true);

        // Create a basic style.css file
        file_put_contents($this->testThemePath . '/style.css', $this->getTestStyleCss());
    }

    protected function tearDown(): void
    {
        // Clean up test theme directory
        if (is_dir($this->testThemePath)) {
            unlink($this->testThemePath . '/style.css');
            rmdir($this->testThemePath);
        }

        parent::tearDown();
    }

    public function test_theme_can_register_itself(): void
    {
        // Act
        $theme = $this->registrar->registerActiveTheme('test-theme', $this->testThemePath);

        // Assert
        $this->assertNotNull($theme);
        $this->assertEquals('test-theme', $theme->getName());
        $this->assertEquals($this->testThemePath, $theme->getPath());
        $this->assertTrue($theme->isEnabled());
    }

    public function test_registered_theme_becomes_active(): void
    {
        // Arrange
        $this->assertNull($this->registrar->getActiveTheme());

        // Act
        $this->registrar->registerActiveTheme('test-theme', $this->testThemePath);

        // Assert
        $activeTheme = $this->registrar->getActiveTheme();
        $this->assertNotNull($activeTheme);
        $this->assertEquals('test-theme', $activeTheme->getName());
    }

    public function test_can_check_if_theme_is_active(): void
    {
        // Arrange
        $this->registrar->registerActiveTheme('test-theme', $this->testThemePath);

        // Assert
        $this->assertTrue($this->registrar->isThemeActive('test-theme'));
        $this->assertTrue($this->registrar->isThemeActive('TEST-THEME')); // Case insensitive
        $this->assertFalse($this->registrar->isThemeActive('other-theme'));
    }

    public function test_only_one_theme_can_be_active(): void
    {
        // Arrange
        $secondThemePath = sys_get_temp_dir() . '/test-theme-2-' . uniqid();
        mkdir($secondThemePath, 0755, true);
        file_put_contents($secondThemePath . '/style.css', $this->getTestStyleCss());

        try {
            // Act
            $this->registrar->registerActiveTheme('first-theme', $this->testThemePath);
            $this->registrar->registerActiveTheme('second-theme', $secondThemePath);

            // Assert - second theme should replace the first
            $activeTheme = $this->registrar->getActiveTheme();
            $this->assertEquals('second-theme', $activeTheme->getName());
            $this->assertFalse($this->registrar->isThemeActive('first-theme'));
            $this->assertTrue($this->registrar->isThemeActive('second-theme'));
        } finally {
            // Cleanup
            unlink($secondThemePath . '/style.css');
            rmdir($secondThemePath);
        }
    }

    public function test_can_reset_active_theme(): void
    {
        // Arrange
        $this->registrar->registerActiveTheme('test-theme', $this->testThemePath);
        $this->assertNotNull($this->registrar->getActiveTheme());

        // Act
        $this->registrar->resetActiveTheme();

        // Assert
        $this->assertNull($this->registrar->getActiveTheme());
        $this->assertFalse($this->registrar->isThemeActive('test-theme'));
    }

    public function test_theme_headers_are_parsed_automatically(): void
    {
        // Act
        $theme = $this->registrar->registerActiveTheme('test-theme', $this->testThemePath);

        // Assert
        $this->assertArrayHasKey('Theme Name', $theme->getHeaders());
        $this->assertEquals('Test Theme', $theme->getHeaders()['Theme Name']);
        $this->assertEquals('1.0.0', $theme->getHeaders()['Version']);
    }

    private function getTestStyleCss(): string
    {
        return <<<CSS
/*
Theme Name: Test Theme
Description: A test theme for the self-registration system
Author: Test Author
Version: 1.0.0
*/

body {
    margin: 0;
}
CSS;
    }
} 