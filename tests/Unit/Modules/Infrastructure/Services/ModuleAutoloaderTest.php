<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;

class ModuleAutoloaderTest extends TestCase
{
    private Container $app;

    private ClassLoader $classLoader;

    private ModuleAutoloader $autoloader;

    protected function setUp(): void
    {
        $this->app = new Container;
        $this->classLoader = $this->createMock(ClassLoader::class);

        // Bind the class loader to the container
        $this->app->instance(ClassLoader::class, $this->classLoader);

        $this->autoloader = new ModuleAutoloader($this->app);
    }

    public function test_it_builds_theme_namespace_correctly(): void
    {
        $tempDir = sys_get_temp_dir().'/test_theme_namespace_'.uniqid();
        $appDir = $tempDir.'/app';
        mkdir($appDir, 0777, true);

        $module = $this->createMockModule('TestTheme', $tempDir);

        $this->classLoader
            ->expects($this->once())
            ->method('addPsr4')
            ->with('Theme\\TestTheme\\', $tempDir.'/app');

        $this->autoloader->registerTheme($module);

        // Cleanup
        rmdir($appDir);
        rmdir($tempDir);
    }

    public function test_it_builds_plugin_namespace_correctly(): void
    {
        $tempDir = sys_get_temp_dir().'/test_plugin_namespace_'.uniqid();
        $appDir = $tempDir.'/app';
        mkdir($appDir, 0777, true);

        $module = $this->createMockModule('TestPlugin', $tempDir);

        $this->classLoader
            ->expects($this->once())
            ->method('addPsr4')
            ->with('Plugin\\TestPlugin\\', $tempDir.'/app');

        $this->autoloader->registerPlugin($module);

        // Cleanup
        rmdir($appDir);
        rmdir($tempDir);
    }

    public function test_it_prefers_app_directory_over_src(): void
    {
        $tempDir = sys_get_temp_dir().'/test_theme_preference_'.uniqid();
        $appDir = $tempDir.'/app';
        $srcDir = $tempDir.'/src';
        mkdir($appDir, 0777, true);
        mkdir($srcDir, 0777, true);

        $module = $this->createMockModule('TestTheme', $tempDir);

        // Mock file system checks
        $this->classLoader
            ->expects($this->once())
            ->method('addPsr4')
            ->with('Theme\\TestTheme\\', $tempDir.'/app');

        $this->autoloader->registerTheme($module);

        // Cleanup
        rmdir($appDir);
        rmdir($srcDir);
        rmdir($tempDir);
    }

    public function test_it_tracks_registered_namespaces(): void
    {
        $tempDir = sys_get_temp_dir().'/test_theme_'.uniqid();
        $appDir = $tempDir.'/app';
        mkdir($appDir, 0777, true);

        $module = $this->createMockModule('TestTheme', $tempDir);

        $this->autoloader->registerTheme($module);

        $this->assertTrue($this->autoloader->isNamespaceRegistered('Theme\\TestTheme\\'));
        $this->assertFalse($this->autoloader->isNamespaceRegistered('Theme\\OtherTheme\\'));

        // Cleanup
        rmdir($appDir);
        rmdir($tempDir);
    }

    public function test_it_does_not_register_duplicate_namespaces(): void
    {
        $tempDir = sys_get_temp_dir().'/test_theme_duplicate_'.uniqid();
        $appDir = $tempDir.'/app';
        mkdir($appDir, 0777, true);

        $module = $this->createMockModule('TestTheme', $tempDir);

        $this->classLoader
            ->expects($this->once())
            ->method('addPsr4');

        // Register the same module twice
        $this->autoloader->registerTheme($module);
        $this->autoloader->registerTheme($module);

        // Cleanup
        rmdir($appDir);
        rmdir($tempDir);
    }

    public function test_it_can_unregister_namespace(): void
    {
        $tempDir = sys_get_temp_dir().'/test_theme_unregister_'.uniqid();
        $appDir = $tempDir.'/app';
        mkdir($appDir, 0777, true);

        $module = $this->createMockModule('TestTheme', $tempDir);

        $this->autoloader->registerTheme($module);
        $this->assertTrue($this->autoloader->isNamespaceRegistered('Theme\\TestTheme\\'));

        $this->autoloader->unregisterNamespace('Theme\\TestTheme\\');
        $this->assertFalse($this->autoloader->isNamespaceRegistered('Theme\\TestTheme\\'));

        // Cleanup
        rmdir($appDir);
        rmdir($tempDir);
    }

    private function createMockModule(string $name, string $path): ModuleInterface
    {
        $module = $this->createMock(ModuleInterface::class);

        $module->method('getStudlyName')
            ->willReturn($name);

        $module->method('getPath')
            ->willReturn($path);

        return $module;
    }
}
