<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discoverer\Infrastructure\Services\Scouts\HookScout;
use Pollora\Hook\Domain\Contracts\Hooks;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Discover;

beforeEach(function () {
    $this->cacheDriver = Mockery::mock(DiscoverCacheDriver::class);
    $this->debugDetector = Mockery::mock(DebugDetectorInterface::class);

    // Create a mock container
    $this->app = Mockery::mock(Container::class);
    $this->app->shouldReceive('has')->andReturn(false);

    $this->scout = new HookScout(
        $this->app,
        [__DIR__, __DIR__.'/modules'] // Array of directories to scan
    );
});

afterEach(function () {
    Mockery::close();
});

test('getType returns hook type', function () {
    expect($this->scout->getType())->toBe('hook');
});

test('getDirectories returns valid directories only', function () {
    $directories = $this->scout->getDirectories();

    expect($directories)->toBeArray()
        ->and($directories)->toContain(__DIR__)
        ->and(count($directories))->toBeLessThanOrEqual(2); // Au maximum 2 chemins si modules existe
});

test('criteria applies AbstractHook class filter', function () {
    // Create a mock of Discover
    $discover = Mockery::mock(Discover::class);

    // Configure the mock for method chaining
    $discover->shouldReceive('implementing')
        ->once()
        ->with(Hooks::class)
        ->andReturnSelf();

    // Use reflection to access the protected method
    $reflection = new ReflectionClass($this->scout);
    $method = $reflection->getMethod('criteria');
    $method->setAccessible(true);

    // Call the criteria method
    $result = $method->invokeArgs($this->scout, [$discover]);

    // Verify that the result is the same object
    expect($result)->toBe($discover);
});
