<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Pollora\Theme\Domain\Models\ThemeModule;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;

class ThemeAutoloaderTest extends TestCase
{
    private Container $app;
    private ClassLoader $classLoader;
    private ThemeAutoloader $autoloader;

    protected function setUp(): void
    {
        $this->app = new Container();
        $this->classLoader = $this->createMock(ClassLoader::class);
        
        // Bind the class loader to the container
        $this->app->instance(ClassLoader::class, $this->classLoader);
        
        $this->autoloader = new ThemeAutoloader($this->app);
    }

    public function test_it_registers_theme_module(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_theme_register_' . uniqid();
        $appDir = $tempDir . '/app';
        mkdir($appDir, 0777, true);
        
        $theme = $this->createMockTheme('Solidarmonde', $tempDir);
        
        $this->classLoader
            ->expects($this->once())
            ->method('addPsr4')
            ->with('Theme\\Solidarmonde\\', $tempDir . '/app');

        $this->autoloader->registerThemeModule($theme);
        
        // Cleanup
        rmdir($appDir);
        rmdir($tempDir);
    }

    public function test_it_gets_theme_namespace(): void
    {
        $namespace = $this->autoloader->getThemeNamespace('TestTheme');
        
        $this->assertEquals('Theme\\TestTheme\\', $namespace);
    }

    public function test_it_checks_if_theme_is_registered(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_theme_check_' . uniqid();
        $appDir = $tempDir . '/app';
        mkdir($appDir, 0777, true);
        
        $theme = $this->createMockTheme('TestTheme', $tempDir);
        
        $this->assertFalse($this->autoloader->isThemeRegistered('TestTheme'));
        
        $this->autoloader->registerThemeModule($theme);
        
        $this->assertTrue($this->autoloader->isThemeRegistered('TestTheme'));
        
        // Cleanup
        rmdir($appDir);
        rmdir($tempDir);
    }

    public function test_it_registers_multiple_themes(): void
    {
        $tempDir1 = sys_get_temp_dir() . '/test_theme_multiple1_' . uniqid();
        $appDir1 = $tempDir1 . '/app';
        mkdir($appDir1, 0777, true);
        
        $tempDir2 = sys_get_temp_dir() . '/test_theme_multiple2_' . uniqid();
        $appDir2 = $tempDir2 . '/app';
        mkdir($appDir2, 0777, true);
        
        $theme1 = $this->createMockTheme('ThemeOne', $tempDir1);
        $theme2 = $this->createMockTheme('ThemeTwo', $tempDir2);
        
        $this->classLoader
            ->expects($this->exactly(2))
            ->method('addPsr4');

        $this->classLoader
            ->expects($this->once())
            ->method('register');

        $this->autoloader->registerThemes([$theme1, $theme2]);
        
        // Cleanup
        rmdir($appDir1);
        rmdir($tempDir1);
        rmdir($appDir2);
        rmdir($tempDir2);
    }

    private function createMockTheme(string $name, string $path): ThemeModule
    {
        $theme = $this->createMock(ThemeModule::class);
        
        $theme->method('getStudlyName')
            ->willReturn($name);
            
        $theme->method('getPath')
            ->willReturn($path);
            
        return $theme;
    }
}