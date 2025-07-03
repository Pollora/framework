<?php

declare(strict_types=1);

namespace Tests\Unit\PostType;

use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRegistryInterface;

beforeEach(function () {
    $this->mockFactory = mock(PostTypeFactoryInterface::class);
    $this->mockRegistry = mock(PostTypeRegistryInterface::class);
    $this->postTypeService = new PostTypeService($this->mockFactory, $this->mockRegistry);
});

test('register calls make on factory', function () {
    // Define test values
    $slug = 'test-post-type';
    $singular = 'Test Post Type';
    $plural = 'Test Post Types';
    $args = ['public' => true];

    $mockPostType = new \stdClass;

    // Configure the mock - register method should only call the factory to create the post type
    // (pollora/entity handles the WordPress registration automatically)
    $this->mockFactory
        ->shouldReceive('make')
        ->with($slug, $singular, $plural, $args)
        ->once()
        ->andReturn($mockPostType);

    // Call the method under test
    $result = $this->postTypeService->register($slug, $singular, $plural, $args);

    // Assert the result
    expect($result)->toBe($mockPostType);
});

test('exists calls exists on registry', function () {
    // Define test values
    $slug = 'test-post-type';

    // Configure the mock
    $this->mockRegistry
        ->shouldReceive('exists')
        ->with($slug)
        ->once()
        ->andReturn(true);

    // Call the method under test
    $result = $this->postTypeService->exists($slug);

    // Assert the result
    expect($result)->toBeTrue();
});

test('getRegistered calls getAll on registry', function () {
    // Define test values
    $registeredPostTypes = ['post' => [], 'page' => [], 'test-post-type' => []];

    // Configure the mock
    $this->mockRegistry
        ->shouldReceive('getAll')
        ->once()
        ->andReturn($registeredPostTypes);

    // Call the method under test
    $result = $this->postTypeService->getRegistered();

    // Assert the result
    expect($result)->toBe($registeredPostTypes);
});
