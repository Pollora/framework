<?php

declare(strict_types=1);

use Illuminate\Container\Container;

beforeEach(function () {
    $this->app = new Container();
});

it('can create container for auto discovery testing', function () {
    expect($this->app)->toBeInstanceOf(Container::class);
});

// Note: This test previously used Orchestra\Testbench\TestCase and tested services that have been refactored:
// - ThemeManifest is now ModuleManifest
// - ThemeManager has been moved to Theme module
// - Auto-discovery is now handled by ThemeServiceProviderScout
//
// The current auto-discovery functionality is tested in:
// - tests/Unit/Theme/Infrastructure/Services/ThemeAutoloaderIntegrationTest.php
// - tests/Unit/Modules/ModuleAutoloadingIntegrationTest.php
// - tests/Unit/Modules/Infrastructure/Services/ModuleBootstrapIntegrationTest.php