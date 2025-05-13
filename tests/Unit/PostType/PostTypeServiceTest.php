<?php

declare(strict_types=1);

namespace Tests\Unit\PostType;

use Pollora\Entity\PostType;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;

beforeEach(function () {
    $this->mockFactory = mock(PostTypeFactoryInterface::class);
    $this->mockPostType = mock(PostType::class);
    $this->postTypeService = new PostTypeService($this->mockFactory);
});

test('register calls make on factory', function () {
    // Define test values
    $slug = 'test-post-type';
    $singular = 'Test Post Type';
    $plural = 'Test Post Types';
    
    // Configure the mock
    $this->mockFactory
        ->shouldReceive('make')
        ->with($slug, $singular, $plural)
        ->once()
        ->andReturn($this->mockPostType);
    
    // Call the method under test
    $result = $this->postTypeService->register($slug, $singular, $plural);
    
    // Assert the result
    expect($result)->toBe($this->mockPostType);
});

test('exists calls exists on factory', function () {
    // Define test values
    $postType = 'test-post-type';
    
    // Configure the mock
    $this->mockFactory
        ->shouldReceive('exists')
        ->with($postType)
        ->once()
        ->andReturn(true);
    
    // Call the method under test
    $result = $this->postTypeService->exists($postType);
    
    // Assert the result
    expect($result)->toBeTrue();
});

test('getRegistered calls getRegistered on factory', function () {
    // Define test values
    $registeredPostTypes = ['post', 'page', 'test-post-type'];
    
    // Configure the mock
    $this->mockFactory
        ->shouldReceive('getRegistered')
        ->once()
        ->andReturn($registeredPostTypes);
    
    // Call the method under test
    $result = $this->postTypeService->getRegistered();
    
    // Assert the result
    expect($result)->toBe($registeredPostTypes);
}); 