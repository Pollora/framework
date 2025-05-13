<?php

declare(strict_types=1);

namespace Tests\Feature\PostType;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pollora\Entity\PostType;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\PostType\PostTypeFactory;
use Pollora\PostType\PostTypeServiceProvider;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Register the service provider manually
    $this->app->register(PostTypeServiceProvider::class);
});

test('post type factory interface is bound to factory implementation', function () {
    // Get the binding from the container
    $factory = $this->app->make(PostTypeFactoryInterface::class);
    
    // Assert it is the correct instance
    expect($factory)->toBeInstanceOf(PostTypeFactory::class);
});

test('post type service is properly instantiated', function () {
    // Get the service from the container
    $service = $this->app->make(PostTypeService::class);
    
    // Assert it is the correct instance
    expect($service)->toBeInstanceOf(PostTypeService::class);
});

test('legacy binding for wp.posttype is available', function () {
    // Get the binding from the container
    $factory = $this->app->make('wp.posttype');
    
    // Assert it is the correct instance
    expect($factory)->toBeInstanceOf(PostTypeFactoryInterface::class);
});

test('post type service can create post types', function () {
    // Create a config entry for testing
    config([
        'post-types' => [
            'test-post' => [
                'names' => [
                    'singular' => 'Test Post',
                    'plural' => 'Test Posts',
                ],
            ],
        ],
    ]);
    
    // Create a test service
    $service = $this->app->make(PostTypeService::class);
    
    // Register a post type
    $postType = $service->register('custom-post', 'Custom Post', 'Custom Posts');
    
    // Assert it is the correct instance
    expect($postType)
        ->toBeInstanceOf(PostType::class)
        ->and($postType->slug)->toBe('custom-post')
        ->and($postType->singular)->toBe('Custom Post')
        ->and($postType->plural)->toBe('Custom Posts');
}); 