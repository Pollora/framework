<?php

declare(strict_types=1);

namespace Tests\Unit\PostType;

use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;

beforeEach(function () {
    setupWordPressMocks();
    $this->factory = new PostTypeFactory;
});

test('make creates new PostType instance with correct parameters', function () {
    // Define test values
    $slug = 'test-post-type';
    $singular = 'Test Post Type';
    $plural = 'Test Post Types';

    // Call the method under test
    $result = $this->factory->make($slug, $singular, $plural);

    // Assert the result is a PostType instance from Entity package
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
});

test('make handles null parameters correctly', function () {
    // Define test values
    $slug = 'test-post-type';

    // Call the method under test with only required parameters
    $result = $this->factory->make($slug);

    // Assert the result
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
});
