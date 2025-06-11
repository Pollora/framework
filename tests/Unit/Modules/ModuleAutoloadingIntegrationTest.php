<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Mockery as m;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Theme\Domain\Models\LaravelThemeModule;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;

beforeEach(function () {
    $this->app = new Container();
    
    // Register autoloader services in the container
    $this->app->singleton(ModuleAutoloader::class, function ($app) {
        return new ModuleAutoloader($app);
    });
    
    $this->app->singleton(ThemeAutoloader::class, function ($app) {
        return new ThemeAutoloader($app);
    });
});

afterEach(function () {
    m::close();
});

it('can create and use module autoloader', function () {
    $autoloader = $this->app->make(ModuleAutoloader::class);
    
    expect($autoloader)->toBeInstanceOf(ModuleAutoloader::class);
    expect($autoloader->getRegisteredNamespaces())->toBeArray()->toBeEmpty();
});

it('can create and use theme autoloader', function () {
    $autoloader = $this->app->make(ThemeAutoloader::class);
    
    expect($autoloader)->toBeInstanceOf(ThemeAutoloader::class);
    expect($autoloader->getThemeNamespace('TestTheme'))->toBe('Theme\\TestTheme\\');
});

it('integrates autoloader with theme module registration', function () {
    // Create temporary directory for this test
    $tempDir = sys_get_temp_dir() . '/pollora_module_test_' . uniqid();
    $themePath = $tempDir . '/themes/test-theme';
    mkdir($themePath . '/app', 0755, true);
    
    // Create a real LaravelThemeModule instance for integration testing
    $theme = new class($tempDir . '/themes', $this->app) extends LaravelThemeModule {
        public function __construct(string $path, $app) {
            parent::__construct('TestTheme', $path, $app);
        }
        
        public function getPath(): string {
            return $this->path . '/test-theme';
        }
        
        // Override methods that depend on Laravel classes not available in test
        protected function registerAliases(): void {}
        protected function registerConfig(): void {}
        protected function registerTranslations(): void {}
        protected function registerFiles(): void {}
    };
    
    // Register the theme (this should set up autoloading)
    $theme->register();
    
    // Verify that the autoloader was used
    $autoloader = $this->app->make(ThemeAutoloader::class);
    expect($autoloader->isThemeRegistered('TestTheme'))->toBeTrue();
    
    // Verify namespace is correct
    expect($autoloader->getThemeNamespace('TestTheme'))->toBe('Theme\\TestTheme\\');
    
    // Clean up
    exec("rm -rf " . escapeshellarg($tempDir));
});