<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\View\ViewFinderInterface;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Domain\Exceptions\ThemeException;
use Pollora\Theme\Domain\Models\ThemeMetadata;

beforeEach(function () {
    // Create mock container with config property
    $this->app = Mockery::mock(\Illuminate\Contracts\Container\Container::class);
    $this->config = Mockery::mock('config');
    $this->config->shouldReceive('get')->withAnyArgs()->andReturn('/test/path');
    $this->app->shouldReceive('offsetGet')->with('config')->andReturn($this->config);
    
    // Mock the Console Detection Service
    $mockConsoleDetectionService = Mockery::mock(\Pollora\Application\Application\Services\ConsoleDetectionService::class);
    $mockConsoleDetectionService->shouldReceive('isConsole')->andReturn(false);
    $mockConsoleDetectionService->shouldReceive('isWpCli')->andReturn(false);
    $this->app->shouldReceive('make')->with(\Pollora\Application\Application\Services\ConsoleDetectionService::class)
        ->andReturn($mockConsoleDetectionService);
    $this->app->shouldReceive('has')->withAnyArgs()->andReturn(true);
    
    // Mock the bind for any dependencies
    $this->app->shouldReceive('bind')->withAnyArgs()->andReturnNull();
    
    // Other required mocks
    $this->viewFinder = Mockery::mock(ViewFinderInterface::class);
    $this->localeLoader = Mockery::mock(Loader::class);

    // Mock running in console
    $this->app->shouldReceive('runningInConsole')->andReturn(false);

    // Create manager
    $this->themeManager = new ThemeManager($this->app, $this->viewFinder, $this->localeLoader);
});

test('loads a valid theme', function () {
    // Create a custom mockery matcher for the test path
    $testPath = '/path/to/themes';

    // Mock ThemeManager with specific methods
    $themeName = 'testTheme';

    // Create a better mock of the container
    $app = Mockery::mock(\Illuminate\Contracts\Container\Container::class);
    $config = Mockery::mock('config');
    $config->shouldReceive('get')->withAnyArgs()->andReturn($testPath);
    $app->shouldReceive('offsetGet')->with('config')->andReturn($config);
    $app->shouldReceive('runningInConsole')->andReturn(true);
    
    // Mock the Console Detection Service
    $mockConsoleDetectionService = Mockery::mock(\Pollora\Application\Application\Services\ConsoleDetectionService::class);
    $mockConsoleDetectionService->shouldReceive('isConsole')->andReturn(false);
    $mockConsoleDetectionService->shouldReceive('isWpCli')->andReturn(false);
    $app->shouldReceive('make')->with(\Pollora\Application\Application\Services\ConsoleDetectionService::class)
        ->andReturn($mockConsoleDetectionService);
    $app->shouldReceive('has')->withAnyArgs()->andReturn(true);
    $app->shouldReceive('bind')->withAnyArgs()->andReturnNull();

    // Create manager with proper mocks
    $manager = Mockery::mock(
        ThemeManager::class,
        [$app, $this->viewFinder, $this->localeLoader]
    )->makePartial();

    $manager->shouldAllowMockingProtectedMethods();

    // Create a mock ThemeMetadata
    $themeMetadata = Mockery::mock(ThemeMetadata::class);
    $themeMetadata->shouldReceive('getName')->andReturn($themeName);
    $themeMetadata->shouldReceive('getBasePath')->andReturn($testPath.'/'.$themeName);
    $themeMetadata->shouldReceive('loadConfiguration')->andReturnNull();
    $themeMetadata->shouldReceive('getParentTheme')->andReturn(null);
    $themeMetadata->shouldReceive('getLanguagePath')->andReturn($testPath.'/'.$themeName.'/lang');

    // Bypass the ThemeMetadata instantiation with a mock
    $manager->shouldReceive('createThemeMetadata')->andReturn($themeMetadata);
    $manager->shouldReceive('registerThemeDirectories')->andReturnNull();
    $manager->shouldReceive('getThemesPath')->andReturn($testPath);

    // Expectation on addNamespace
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

    // Create a better mock of the container
    $app = Mockery::mock(\Illuminate\Contracts\Container\Container::class);
    $config = Mockery::mock('config');
    $config->shouldReceive('get')->withAnyArgs()->andReturn('/path/to/themes');
    $app->shouldReceive('offsetGet')->with('config')->andReturn($config);
    $app->shouldReceive('runningInConsole')->andReturn(false);
    
    // Mock the Console Detection Service
    $mockConsoleDetectionService = Mockery::mock(\Pollora\Application\Application\Services\ConsoleDetectionService::class);
    $mockConsoleDetectionService->shouldReceive('isConsole')->andReturn(false);
    $mockConsoleDetectionService->shouldReceive('isWpCli')->andReturn(false);
    $app->shouldReceive('make')->with(\Pollora\Application\Application\Services\ConsoleDetectionService::class)
        ->andReturn($mockConsoleDetectionService);
    $app->shouldReceive('has')->withAnyArgs()->andReturn(true);
    $app->shouldReceive('bind')->withAnyArgs()->andReturnNull();

    // Create manager with proper mocks
    $manager = Mockery::mock(
        ThemeManager::class,
        [$app, $this->viewFinder, $this->localeLoader]
    )->makePartial();

    $manager->shouldAllowMockingProtectedMethods();

    // Create a mock ThemeMetadata
    $themeMetadata = Mockery::mock(ThemeMetadata::class);
    $themeMetadata->shouldReceive('getName')->andReturn($themeName);
    $themeMetadata->shouldReceive('getBasePath')->andReturn('/path/to/themes/'.$themeName);

    // Bypass the ThemeMetadata instantiation with a mock
    $manager->shouldReceive('createThemeMetadata')->andReturn($themeMetadata);
    $manager->shouldReceive('getThemesPath')->andReturn('/path/to/themes');

    expect(fn () => $manager->load($themeName))
        ->toThrow(ThemeException::class, "Theme directory {$themeName} not found.");
});

test('instance returns self', function () {
    expect($this->themeManager->instance())->toBeInstanceOf(ThemeManager::class);
});
