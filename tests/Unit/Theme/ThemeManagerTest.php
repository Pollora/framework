<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\View\ViewFinderInterface;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Domain\Exceptions\ThemeException;
use Pollora\Theme\Domain\Models\ThemeMetadata;
use Pollora\Application\Application\Services\ConsoleDetectionService;

beforeEach(function () {
    // Create mock container with config property
    $this->app = Mockery::mock(Container::class);
    $this->config = Mockery::mock('config');
    $this->config->shouldReceive('get')->withAnyArgs()->andReturn('/test/path');
    $this->app->shouldReceive('offsetGet')->with('config')->andReturn($this->config);
    $this->app->shouldReceive('has')->withAnyArgs()->andReturn(true);
    $this->app->shouldReceive('bind')->withAnyArgs()->andReturnNull();
    $this->viewFinder = Mockery::mock(ViewFinderInterface::class);
    $this->localeLoader = Mockery::mock(Loader::class);
    $this->consoleDetectionService = Mockery::mock(ConsoleDetectionService::class);
    $this->consoleDetectionService->shouldReceive('isConsole')->andReturn(true);
    $this->themeManager = new ThemeManager($this->app, $this->viewFinder, $this->localeLoader, $this->consoleDetectionService);
});

test('loads a valid theme', function () {
    $testPath = '/path/to/themes';
    $themeName = 'testTheme';
    $app = Mockery::mock(Container::class);
    $config = Mockery::mock('config');
    $config->shouldReceive('get')->withAnyArgs()->andReturn($testPath);
    $app->shouldReceive('offsetGet')->with('config')->andReturn($config);
    $app->shouldReceive('runningInConsole')->andReturn(true);
    $app->shouldReceive('has')->withAnyArgs()->andReturn(true);
    $app->shouldReceive('bind')->withAnyArgs()->andReturnNull();
    $consoleDetectionService = Mockery::mock(ConsoleDetectionService::class);
    $consoleDetectionService->shouldReceive('isConsole')->andReturn(true);
    $manager = Mockery::mock(
        ThemeManager::class,
        [$app, $this->viewFinder, $this->localeLoader, $consoleDetectionService]
    )->makePartial();
    $manager->shouldAllowMockingProtectedMethods();
    $themeMetadata = Mockery::mock(ThemeMetadata::class);
    $themeMetadata->shouldReceive('getName')->andReturn($themeName);
    $themeMetadata->shouldReceive('getBasePath')->andReturn($testPath.'/'.$themeName);
    $themeMetadata->shouldReceive('loadConfiguration')->andReturnNull();
    $themeMetadata->shouldReceive('getParentTheme')->andReturn(null);
    $themeMetadata->shouldReceive('getLanguagePath')->andReturn($testPath.'/'.$themeName.'/lang');
    $manager->shouldReceive('createThemeMetadata')->andReturn($themeMetadata);
    $manager->shouldReceive('registerThemeDirectories')->andReturnNull();
    $manager->shouldReceive('getThemesPath')->andReturn($testPath);
    $this->localeLoader->shouldReceive('addNamespace')->with($themeName, Mockery::any())->andReturnNull();
    $manager->load($themeName);
    expect($manager->instance())->toBeInstanceOf(ThemeManager::class);
});

test('throws an exception if theme name is empty', function () {
    expect(fn () => $this->themeManager->load(''))->toThrow(ThemeException::class)
        ->and(fn () => $this->themeManager->load('0'))->toThrow(ThemeException::class);
});

test('throws an exception if theme directory does not exist', function () {
    $themeName = 'nonexistent';
    $app = Mockery::mock(Container::class);
    $config = Mockery::mock('config');
    $config->shouldReceive('get')->withAnyArgs()->andReturn('/path/to/themes');
    $app->shouldReceive('offsetGet')->with('config')->andReturn($config);
    $app->shouldReceive('has')->withAnyArgs()->andReturn(true);
    $app->shouldReceive('bind')->withAnyArgs()->andReturnNull();
    $consoleDetectionService = Mockery::mock(ConsoleDetectionService::class);
    $consoleDetectionService->shouldReceive('isConsole')->andReturn(false);
    $manager = Mockery::mock(
        ThemeManager::class,
        [$app, $this->viewFinder, $this->localeLoader, $consoleDetectionService]
    )->makePartial();
    $manager->shouldAllowMockingProtectedMethods();
    $themeMetadata = Mockery::mock(ThemeMetadata::class);
    $themeMetadata->shouldReceive('getName')->andReturn($themeName);
    $themeMetadata->shouldReceive('getBasePath')->andReturn('/path/to/themes/'.$themeName);
    $manager->shouldReceive('createThemeMetadata')->andReturn($themeMetadata);
    $manager->shouldReceive('getThemesPath')->andReturn('/path/to/themes');
    expect(fn () => $manager->load($themeName))
        ->toThrow(ThemeException::class, "Theme directory {$themeName} not found.");
});

test('instance returns self', function () {
    expect($this->themeManager->instance())->toBeInstanceOf(ThemeManager::class);
});
