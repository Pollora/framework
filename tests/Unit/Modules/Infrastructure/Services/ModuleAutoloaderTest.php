<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;

describe('ModuleAutoloader', function (): void {
    beforeEach(function (): void {
        $this->app = new Container;
        $this->classLoader = Mockery::mock(ClassLoader::class)->shouldIgnoreMissing();
        $this->app->instance(ClassLoader::class, $this->classLoader);
        $this->autoloader = new ModuleAutoloader($this->app);
    });

    it('builds theme namespace correctly', function (): void {
        $tempDir = sys_get_temp_dir().'/test_theme_namespace_'.uniqid();
        mkdir($tempDir.'/app', 0777, true);

        $module = createMockModuleForAutoloader('TestTheme', $tempDir);

        $this->classLoader->shouldReceive('addPsr4')->once()->with('Theme\\TestTheme\\', $tempDir.'/app');

        $this->autoloader->registerTheme($module);

        rmdir($tempDir.'/app');
        rmdir($tempDir);
    });

    it('builds plugin namespace correctly', function (): void {
        $tempDir = sys_get_temp_dir().'/test_plugin_namespace_'.uniqid();
        mkdir($tempDir.'/app', 0777, true);

        $module = createMockModuleForAutoloader('TestPlugin', $tempDir);

        $this->classLoader->shouldReceive('addPsr4')->once()->with('Plugin\\TestPlugin\\', $tempDir.'/app');

        $this->autoloader->registerPlugin($module);

        rmdir($tempDir.'/app');
        rmdir($tempDir);
    });

    it('prefers app directory over src', function (): void {
        $tempDir = sys_get_temp_dir().'/test_theme_preference_'.uniqid();
        mkdir($tempDir.'/app', 0777, true);
        mkdir($tempDir.'/src', 0777, true);

        $module = createMockModuleForAutoloader('TestTheme', $tempDir);

        $this->classLoader->shouldReceive('addPsr4')->once()->with('Theme\\TestTheme\\', $tempDir.'/app');

        $this->autoloader->registerTheme($module);

        rmdir($tempDir.'/app');
        rmdir($tempDir.'/src');
        rmdir($tempDir);
    });

    it('tracks registered namespaces', function (): void {
        $tempDir = sys_get_temp_dir().'/test_theme_'.uniqid();
        mkdir($tempDir.'/app', 0777, true);

        $module = createMockModuleForAutoloader('TestTheme', $tempDir);

        $this->autoloader->registerTheme($module);

        expect($this->autoloader->isNamespaceRegistered('Theme\\TestTheme\\'))->toBeTrue();
        expect($this->autoloader->isNamespaceRegistered('Theme\\OtherTheme\\'))->toBeFalse();

        rmdir($tempDir.'/app');
        rmdir($tempDir);
    });

    it('does not register duplicate namespaces', function (): void {
        $tempDir = sys_get_temp_dir().'/test_theme_duplicate_'.uniqid();
        mkdir($tempDir.'/app', 0777, true);

        $module = createMockModuleForAutoloader('TestTheme', $tempDir);

        $this->classLoader->shouldReceive('addPsr4')->once();

        $this->autoloader->registerTheme($module);
        $this->autoloader->registerTheme($module);

        rmdir($tempDir.'/app');
        rmdir($tempDir);
    });

    it('can unregister namespace', function (): void {
        $tempDir = sys_get_temp_dir().'/test_theme_unregister_'.uniqid();
        mkdir($tempDir.'/app', 0777, true);

        $module = createMockModuleForAutoloader('TestTheme', $tempDir);

        $this->autoloader->registerTheme($module);
        expect($this->autoloader->isNamespaceRegistered('Theme\\TestTheme\\'))->toBeTrue();

        $this->autoloader->unregisterNamespace('Theme\\TestTheme\\');
        expect($this->autoloader->isNamespaceRegistered('Theme\\TestTheme\\'))->toBeFalse();

        rmdir($tempDir.'/app');
        rmdir($tempDir);
    });
});

describe('ModuleAutoloader::register()', function (): void {
    beforeEach(function (): void {
        $this->rootLoader = new ClassLoader('/fake-project/vendor');
        $this->pluginLoader = new ClassLoader('/fake-project/public/content/plugins/query-monitor/vendor');
    });

    afterEach(function (): void {
        $this->rootLoader->unregister();
        $this->pluginLoader->unregister();
    });

    it('keeps the root loader ahead of a plugin loader registered after it', function (): void {
        // Composer registers the root loader prepended; a plugin such as
        // Query Monitor appends its own when WordPress loads it.
        $this->rootLoader->register(true);
        $this->pluginLoader->register(false);

        $app = new Container;
        $app->instance(ClassLoader::class, $this->rootLoader);

        (new ModuleAutoloader($app))->register();

        // Application::inferBasePath() reads the first key of this list.
        expect(array_key_first(ClassLoader::getRegisteredLoaders()))->toBe('/fake-project/vendor');
    });

    it('registers a loader that is not registered yet', function (): void {
        $app = new Container;
        $app->instance(ClassLoader::class, $this->rootLoader);

        (new ModuleAutoloader($app))->register();

        expect(spl_autoload_functions())->toContain([$this->rootLoader, 'loadClass'])
            ->and(ClassLoader::getRegisteredLoaders())->toHaveKey('/fake-project/vendor');
    });
});

function createMockModuleForAutoloader(string $name, string $path): ModuleInterface
{
    $module = Mockery::mock(ModuleInterface::class);
    $module->shouldReceive('getStudlyName')->andReturn($name);
    $module->shouldReceive('getPath')->andReturn($path);

    return $module;
}
