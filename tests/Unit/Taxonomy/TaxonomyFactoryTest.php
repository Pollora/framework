<?php

declare(strict_types=1);

namespace Tests\Unit\Taxonomy;

use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;

beforeEach(function (): void {
    $this->factory = new TaxonomyFactory;
});

test('make creates new Taxonomy instance with correct parameters', function (): void {
    $slug = 'test-taxonomy';
    $objectType = ['post', 'page'];
    $singular = 'Test Taxonomy';
    $plural = 'Test Taxonomies';

    $result = $this->factory->make($slug, $objectType, $singular, $plural);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug)
        ->and($result->getObjectType())->toBe($objectType);
});

test('make handles null parameters correctly', function (): void {
    $slug = 'test-taxonomy';
    $objectType = 'post';

    $result = $this->factory->make($slug, $objectType);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug)
        ->and($result->getObjectType())->toBe($objectType);
});

test('make generates singular name from slug when not provided', function (): void {
    $slug = 'product_category';
    $objectType = 'product';

    $result = $this->factory->make($slug, $objectType);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
});

test('make generates plural name from singular when not provided', function (): void {
    $slug = 'category';
    $objectType = 'post';
    $singular = 'Category';

    $result = $this->factory->make($slug, $objectType, $singular);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);
});

test('make handles string object type', function (): void {
    $slug = 'tag';
    $objectType = 'post';

    $result = $this->factory->make($slug, $objectType);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug)
        ->and($result->getObjectType())->toBe($objectType);
});

test('make handles array object type', function (): void {
    $slug = 'category';
    $objectType = ['post', 'page', 'product'];

    $result = $this->factory->make($slug, $objectType);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug)
        ->and($result->getObjectType())->toBe($objectType);
});

test('make applies additional arguments when provided', function (): void {
    $slug = 'test-taxonomy';
    $objectType = 'post';
    $singular = 'Test Taxonomy';
    $plural = 'Test Taxonomies';
    $args = [
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
    ];

    $result = $this->factory->make($slug, $objectType, $singular, $plural, $args);

    expect($result)
        ->toBeObject()
        ->and($result->getSlug())->toBe($slug);

    expect($result->getObjectType())->toBe($objectType);
});
