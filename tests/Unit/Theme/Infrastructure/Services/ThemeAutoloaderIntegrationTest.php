<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Theme\Domain\Models\ThemeModule;
use Pollora\Theme\Infrastructure\Services\ThemeAutoloader;

beforeEach(function () {
    $this->app = new Container;
    $this->autoloader = new ThemeAutoloader($this->app);

    // Create temporary directory structure for testing
    $this->tempDir = sys_get_temp_dir().'/pollora_test_'.uniqid();
    $this->themePath = $this->tempDir.'/themes/test-theme';
    mkdir($this->themePath.'/app', 0755, true);

    // Create a mock theme module for testing
    $this->theme = new class($this->themePath) extends ThemeModule
    {
        public function __construct(string $path)
        {
            parent::__construct('TestTheme', dirname($path));
        }

        public function getStudlyName(): string
        {
            return 'TestTheme';
        }

        public function getPath(): string
        {
            return $this->path.'/test-theme';
        }
    };
});

afterEach(function () {
    // Clean up temporary directory
    if (is_dir($this->tempDir)) {
        exec('rm -rf '.escapeshellarg($this->tempDir));
    }
});

it('generates correct namespace for theme', function () {
    $namespace = $this->autoloader->getThemeNamespace('TestTheme');

    expect($namespace)->toBe('Theme\\TestTheme\\');
});

it('tracks theme registration status', function () {
    // Initially not registered
    expect($this->autoloader->isThemeRegistered('TestTheme'))->toBeFalse();

    // Register the theme
    $this->autoloader->registerThemeModule($this->theme);

    // Now should be registered
    expect($this->autoloader->isThemeRegistered('TestTheme'))->toBeTrue();
});

it('generates correct namespace for different theme names', function () {
    $testCases = [
        'Solidarmonde' => 'Theme\\Solidarmonde\\',
        'MyCustomTheme' => 'Theme\\MyCustomTheme\\',
        'BlogTheme' => 'Theme\\BlogTheme\\',
    ];

    foreach ($testCases as $themeName => $expectedNamespace) {
        expect($this->autoloader->getThemeNamespace($themeName))->toBe($expectedNamespace);
    }
});

it('can register multiple themes without conflicts', function () {
    // Create directory structure for additional themes
    $theme1Path = $this->tempDir.'/themes/theme-one';
    $theme2Path = $this->tempDir.'/themes/theme-two';
    mkdir($theme1Path.'/app', 0755, true);
    mkdir($theme2Path.'/app', 0755, true);

    // Create multiple themes
    $theme1 = new class($theme1Path) extends ThemeModule
    {
        public function __construct(string $path)
        {
            parent::__construct('ThemeOne', dirname($path));
        }

        public function getStudlyName(): string
        {
            return 'ThemeOne';
        }

        public function getPath(): string
        {
            return $this->path.'/theme-one';
        }
    };

    $theme2 = new class($theme2Path) extends ThemeModule
    {
        public function __construct(string $path)
        {
            parent::__construct('ThemeTwo', dirname($path));
        }

        public function getStudlyName(): string
        {
            return 'ThemeTwo';
        }

        public function getPath(): string
        {
            return $this->path.'/theme-two';
        }
    };

    // Register both themes
    $this->autoloader->registerThemeModule($theme1);
    $this->autoloader->registerThemeModule($theme2);

    // Both should be registered
    expect($this->autoloader->isThemeRegistered('ThemeOne'))->toBeTrue();
    expect($this->autoloader->isThemeRegistered('ThemeTwo'))->toBeTrue();

    // Verify correct namespaces
    expect($this->autoloader->getThemeNamespace('ThemeOne'))->toBe('Theme\\ThemeOne\\');
    expect($this->autoloader->getThemeNamespace('ThemeTwo'))->toBe('Theme\\ThemeTwo\\');
});

it('handles theme registration idempotently', function () {
    // Register the same theme multiple times
    $this->autoloader->registerThemeModule($this->theme);
    $this->autoloader->registerThemeModule($this->theme);
    $this->autoloader->registerThemeModule($this->theme);

    // Should still be registered only once
    expect($this->autoloader->isThemeRegistered('TestTheme'))->toBeTrue();

    // Should have only one namespace registration
    $registeredNamespaces = $this->autoloader->getRegisteredNamespaces();
    $themeNamespaces = array_filter(array_keys($registeredNamespaces), function ($ns) {
        return str_starts_with($ns, 'Theme\\TestTheme\\');
    });

    expect(count($themeNamespaces))->toBe(1);
});

it('can register themes using batch method', function () {
    // Create directory structure for batch themes
    $batch1Path = $this->tempDir.'/themes/batch-theme-1';
    $batch2Path = $this->tempDir.'/themes/batch-theme-2';
    mkdir($batch1Path.'/app', 0755, true);
    mkdir($batch2Path.'/app', 0755, true);

    $theme1 = new class($batch1Path) extends ThemeModule
    {
        public function __construct(string $path)
        {
            parent::__construct('BatchTheme1', dirname($path));
        }

        public function getStudlyName(): string
        {
            return 'BatchTheme1';
        }

        public function getPath(): string
        {
            return $this->path.'/batch-theme-1';
        }
    };

    $theme2 = new class($batch2Path) extends ThemeModule
    {
        public function __construct(string $path)
        {
            parent::__construct('BatchTheme2', dirname($path));
        }

        public function getStudlyName(): string
        {
            return 'BatchTheme2';
        }

        public function getPath(): string
        {
            return $this->path.'/batch-theme-2';
        }
    };

    // Register multiple themes at once
    $this->autoloader->registerThemes([$theme1, $theme2]);

    // Both should be registered
    expect($this->autoloader->isThemeRegistered('BatchTheme1'))->toBeTrue();
    expect($this->autoloader->isThemeRegistered('BatchTheme2'))->toBeTrue();
});

it('returns empty registered namespaces initially', function () {
    $namespaces = $this->autoloader->getRegisteredNamespaces();

    expect($namespaces)->toBeArray()->toBeEmpty();
});

it('tracks registered namespaces after registration', function () {
    $this->autoloader->registerThemeModule($this->theme);

    $namespaces = $this->autoloader->getRegisteredNamespaces();

    expect($namespaces)->toBeArray()->not->toBeEmpty();
    expect($namespaces)->toHaveKey('Theme\\TestTheme\\');
});
