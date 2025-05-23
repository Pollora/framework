<?php

declare(strict_types=1);

namespace Tests\Unit\Taxonomy;

use Pollora\Entity\Taxonomy;
use Pollora\Taxonomy\Application\Services\TaxonomyService;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyRegistryInterface;

beforeEach(function () {
    $this->mockFactory = mock(TaxonomyFactoryInterface::class);
    $this->mockRegistry = mock(TaxonomyRegistryInterface::class);
    $this->mockTaxonomy = mock(Taxonomy::class);
    $this->taxonomyService = new TaxonomyService($this->mockFactory, $this->mockRegistry);
});

test('register calls make on factory', function () {
    // Define test values
    $slug = 'test-taxonomy';
    $objectType = 'post';
    $singular = 'Test Taxonomy';
    $plural = 'Test Taxonomies';

    // Configure the mock factory
    $this->mockFactory
        ->shouldReceive('make')
        ->with($slug, $objectType, $singular, $plural)
        ->once()
        ->andReturn($this->mockTaxonomy);

    // Configure the mock registry
    $this->mockRegistry
        ->shouldReceive('register')
        ->with($this->mockTaxonomy)
        ->once()
        ->andReturn(true);

    // Call the method under test
    $result = $this->taxonomyService->register($slug, $objectType, $singular, $plural);

    // Assert the result
    expect($result)->toBe($this->mockTaxonomy);
});

test('exists calls exists on registry', function () {
    // Define test values
    $slug = 'test-taxonomy';

    // Configure the mock
    $this->mockRegistry
        ->shouldReceive('exists')
        ->with($slug)
        ->once()
        ->andReturn(true);

    // Call the method under test
    $result = $this->taxonomyService->exists($slug);

    // Assert the result
    expect($result)->toBeTrue();
});

test('getRegistered calls getAll on registry', function () {
    // Define test values
    $registeredTaxonomies = ['category', 'tag', 'test-taxonomy'];

    // Configure the mock
    $this->mockRegistry
        ->shouldReceive('getAll')
        ->once()
        ->andReturn($registeredTaxonomies);

    // Call the method under test
    $result = $this->taxonomyService->getRegistered();

    // Assert the result
    expect($result)->toBe($registeredTaxonomies);
});
