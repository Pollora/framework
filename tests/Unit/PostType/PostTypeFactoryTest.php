<?php

declare(strict_types=1);

namespace Tests\Unit\PostType;

use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;

beforeEach(function (): void {
    $this->factory = new PostTypeFactory;
});

test('make creates new PostType instance with correct parameters', function (): void {
    $slug = 'test-post-type';
    $singular = 'Test Post Type';
    $plural = 'Test Post Types';

    $result = $this->factory->make($slug, $singular, $plural);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
});

test('make handles null parameters correctly', function (): void {
    $slug = 'test-post-type';

    $result = $this->factory->make($slug);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
});
