<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use Pollora\Theme\Domain\Models\ThemeModule;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;

describe('ThemeAutoloader', function (): void {
    beforeEach(function (): void {
        $this->app = new Container;
        $this->classLoader = Mockery::mock(ClassLoader::class)->shouldIgnoreMissing();
        $this->app->instance(ClassLoader::class, $this->classLoader);
        $this->autoloader = new ThemeAutoloader($this->app);
    });

    it('registers theme module', function (): void {
        $tempDir = sys_get_temp_dir().'/test_theme_register_'.uniqid();
        $appDir = $tempDir.'/app';
        mkdir($appDir, 0777, true);

        $theme = createMockTheme('Solidarmonde', $tempDir);

        $this->classLoader->shouldReceive('addPsr4')
            ->once()
            ->with('Theme\\Solidarmonde\\', $tempDir.'/app');

        $this->autoloader->registerThemeModule($theme);

        rmdir($appDir);
        rmdir($tempDir);
    });

    it('gets theme namespace', function (): void {
        expect($this->autoloader->getThemeNamespace('TestTheme'))->toBe('Theme\\TestTheme\\');
    });

    it('checks if theme is registered', function (): void {
        $tempDir = sys_get_temp_dir().'/test_theme_check_'.uniqid();
        $appDir = $tempDir.'/app';
        mkdir($appDir, 0777, true);

        $theme = createMockTheme('TestTheme', $tempDir);

        expect($this->autoloader->isThemeRegistered('TestTheme'))->toBeFalse();

        $this->autoloader->registerThemeModule($theme);

        expect($this->autoloader->isThemeRegistered('TestTheme'))->toBeTrue();

        rmdir($appDir);
        rmdir($tempDir);
    });

    it('registers multiple themes', function (): void {
        $tempDir1 = sys_get_temp_dir().'/test_theme_multiple1_'.uniqid();
        mkdir($tempDir1.'/app', 0777, true);

        $tempDir2 = sys_get_temp_dir().'/test_theme_multiple2_'.uniqid();
        mkdir($tempDir2.'/app', 0777, true);

        $theme1 = createMockTheme('ThemeOne', $tempDir1);
        $theme2 = createMockTheme('ThemeTwo', $tempDir2);

        $this->classLoader->shouldReceive('addPsr4')->twice();
        $this->classLoader->shouldReceive('register')->once();

        $this->autoloader->registerThemes([$theme1, $theme2]);

        rmdir($tempDir1.'/app');
        rmdir($tempDir1);
        rmdir($tempDir2.'/app');
        rmdir($tempDir2);
    });
});

function createMockTheme(string $name, string $path): ThemeModule
{
    $theme = Mockery::mock(ThemeModule::class);
    $theme->shouldReceive('getStudlyName')->andReturn($name);
    $theme->shouldReceive('getPath')->andReturn($path);

    return $theme;
}
