<?php

declare(strict_types=1);

namespace Tests\Unit\PostType;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Entity\PostType;
use Pollora\PostType\PostTypeFactory;

beforeEach(function () {
    $this->mockApp = mock(Application::class);
    $this->factory = new PostTypeFactory($this->mockApp);
});

test('make creates new PostType instance with correct parameters', function () {
    // Define test values
    $slug = 'test-post-type';
    $singular = 'Test Post Type';
    $plural = 'Test Post Types';

    // Call the method under test
    $result = $this->factory->make($slug, $singular, $plural);

    // Assert the result is a PostType instance
    expect($result)
        ->toBeInstanceOf(PostType::class)
        ->and($result->slug)->toBe($slug)
        ->and($result->singular)->toBe($singular)
        ->and($result->plural)->toBe($plural);
});

test('make handles null parameters correctly', function () {
    // Define test values
    $slug = 'test-post-type';

    // Call the method under test with only required parameters
    $result = $this->factory->make($slug);

    // Assert the result
    expect($result)
        ->toBeInstanceOf(PostType::class)
        ->and($result->slug)->toBe($slug)
        ->and($result->singular)->toBeNull()
        ->and($result->plural)->toBeNull();
});
