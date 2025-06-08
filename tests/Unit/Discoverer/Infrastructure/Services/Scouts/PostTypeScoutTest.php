<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Pollora\Discoverer\Infrastructure\Services\Scouts\PostTypeScout;
use Pollora\PostType\Domain\Models\AbstractPostType;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;
use Spatie\StructureDiscoverer\Discover;

beforeEach(function () {
    $this->cacheDriver = Mockery::mock(DiscoverCacheDriver::class);

    // Create a mock container
    $this->app = Mockery::mock(Container::class);
    $this->app->shouldReceive('has')->andReturn(false);

    $this->scout = new PostTypeScout(
        $this->app,
        [__DIR__, __DIR__.'/modules'] // Array of directories to scan
    );
});

afterEach(function () {
    Mockery::close();
});

test('getType returns post_type type', function () {
    expect($this->scout->getType())->toBe('post_type');
});

test('getDirectories returns valid directories only', function () {
    $directories = $this->scout->getDirectories();

    expect($directories)->toBeArray()
        ->and($directories)->toContain(__DIR__)
        ->and(count($directories))->toBeLessThanOrEqual(2); // At most 2 paths if modules exist
});

test('criteria applies AbstractPostType class filter', function () {
    // Create a mock of Discover
    $discover = Mockery::mock(Discover::class);

    // Configure the mock for method chaining
    $discover->shouldReceive('extending')
        ->once()
        ->with(AbstractPostType::class)
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
