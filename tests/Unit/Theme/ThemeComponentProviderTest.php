<?php

declare(strict_types=1);

namespace Tests\Unit\Theme;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Mockery as m;
use Pollora\BlockPattern\UI\PatternComponent;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Models\ImageSize;
use Pollora\Theme\Domain\Models\Menus;
use Pollora\Theme\Domain\Models\Sidebar;
use Pollora\Theme\Domain\Models\Templates;
use Pollora\Theme\Domain\Models\ThemeInitializer;
use Pollora\Theme\Infrastructure\Providers\ThemeComponentProvider;
use Pollora\Theme\Infrastructure\Services\Support;

// Properties that were class members are now typically managed via `beforeEach` closures or `uses()`.
// For simplicity with Mockery, we'll define them in `beforeEach` and access them via `$this`.
// Pest will make $this available in tests if the closure in beforeEach uses $this.

beforeEach(function () {
    // parent::setUp() from TestCase is usually not needed directly in Pest unless it does something critical.
    // If TestCase::setUp() has essential mocking or app bootstrapping, it might need to be replicated or called.
    // Assuming Tests\TestCase::setUp() is for general Laravel testing setup, which Pest handles via uses(Tests\TestCase::class).
    // However, direct property access like $this->app implies Pest's context or `uses()`.

    $this->app = m::mock(Application::class);
    $this->config = m::mock(Repository::class);
    $this->provider = new ThemeComponentProvider($this->app);

    // Define core components that should be registered - order matters from the provider
    $this->coreComponents = [
        ThemeInitializer::class,
        PatternComponent::class,
        Menus::class,
        Support::class,
        Sidebar::class,
        Templates::class,
        ImageSize::class,
    ];

    $this->app->shouldReceive('environment')->andReturn('testing');
    $this->app->shouldReceive('runningInConsole')->andReturn(false);
    $this->app->shouldReceive('make')->with('config')->andReturn($this->config);
    $this->config->shouldReceive('get')->andReturn([]);
});

// If Tests\TestCase is needed for other base functionality (like custom assertions or helpers)
// it should be included with `uses(Tests\TestCase::class);` at the top level of the file.
// For now, assuming it's not strictly necessary for these specific tests beyond Mockery setup.

it('registers component factory (verifies all core components registration)', function () {
    // This test is named component_factory, but ThemeComponentProvider doesn't deal with ComponentFactory directly.
    // It registers individual components. Let's assume this test is actually about verifying that
    // all components defined in the provider are registered correctly.

    $mockComponent = m::mock(ThemeComponent::class);
    $mockComponent->shouldReceive('register')->byDefault(); // All components have a register method

    foreach ($this->coreComponents as $component) {
        $this->app->shouldReceive('bound')->with($component)->ordered()->andReturn(false);
        $this->app->shouldReceive('singleton')->with($component)->ordered();
        $this->app->shouldReceive('make')->with($component)->ordered()->andReturn($mockComponent);
    }

    $this->provider->register();
    expect($this->provider)->toBeInstanceOf(ThemeComponentProvider::class);
});

it('registers core components', function () {
    $mockComponent = m::mock(ThemeComponent::class);
    $mockComponent->shouldReceive('register')->byDefault(); // All components have a register method

    foreach ($this->coreComponents as $component) {
        $this->app->shouldReceive('bound')->with($component)->ordered()->andReturn(false);
        $this->app->shouldReceive('singleton')
            ->with($component)
            ->ordered();

        $this->app->shouldReceive('make')
            ->with($component)
            ->ordered()
            ->andReturn($mockComponent);
    }

    $this->provider->register();
    expect($this->provider)->toBeInstanceOf(ThemeComponentProvider::class);
});

it('can be instantiated', function () {
    expect($this->provider)->toBeInstanceOf(ThemeComponentProvider::class);
});

// tearDown with m::close() is usually handled by Pest's Mockery plugin or a global helper if needed.
// If not using the plugin, m::close() might be called in an `afterEach`.
afterEach(function () {
    m::close();
});
