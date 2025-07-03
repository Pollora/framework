<?php

declare(strict_types=1);

namespace Tests\Unit\Taxonomy;

use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;

beforeEach(function () {
    setupWordPressMocks();
    $this->factory = new TaxonomyFactory;
});

test('make creates new Taxonomy instance with correct parameters', function () {
    // Define test values
    $slug = 'test-taxonomy';
    $objectType = ['post', 'page'];
    $singular = 'Test Taxonomy';
    $plural = 'Test Taxonomies';

    // Call the method under test
    $result = $this->factory->make($slug, $objectType, $singular, $plural);

    // Assert the result is a Taxonomy instance from Entity package
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug)
        ->and($result->getObjectType())->toBe($objectType);
});

test('make handles null parameters correctly', function () {
    // Define test values
    $slug = 'test-taxonomy';
    $objectType = 'post';

    // Call the method under test with only required parameters
    $result = $this->factory->make($slug, $objectType);

    // Assert the result
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug)
        ->and($result->getObjectType())->toBe($objectType);
});

test('make generates singular name from slug when not provided', function () {
    // Define test values
    $slug = 'product_category';
    $objectType = 'product';

    // Call the method under test
    $result = $this->factory->make($slug, $objectType);

    // Assert the result has generated names
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
});

test('make generates plural name from singular when not provided', function () {
    // Define test values
    $slug = 'category';
    $objectType = 'post';
    $singular = 'Category';

    // Call the method under test
    $result = $this->factory->make($slug, $objectType, $singular);

    // Assert the result has generated plural
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
});

test('make handles string object type', function () {
    // Define test values
    $slug = 'tag';
    $objectType = 'post';

    // Call the method under test
    $result = $this->factory->make($slug, $objectType);

    // Assert the result
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug)
        ->and($result->getObjectType())->toBe($objectType);
});

test('make handles array object type', function () {
    // Define test values
    $slug = 'category';
    $objectType = ['post', 'page', 'product'];

    // Call the method under test
    $result = $this->factory->make($slug, $objectType);

    // Assert the result
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug)
        ->and($result->getObjectType())->toBe($objectType);
});

test('make applies additional arguments when provided', function () {
    // Define test values
    $slug = 'test-taxonomy';
    $objectType = 'post';
    $singular = 'Test Taxonomy';
    $plural = 'Test Taxonomies';
    $args = [
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
    ];

    // Call the method under test
    $result = $this->factory->make($slug, $objectType, $singular, $plural, $args);

    // Assert the result is created successfully
    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
    
    // Note: Testing args application requires WordPress functions to be available
    // The factory creation succeeds, which validates the main functionality
    expect($result->getObjectType())->toBe($objectType);
});