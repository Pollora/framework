<?php

declare(strict_types=1);

namespace Tests\Unit\Taxonomy;

use Pollora\Entity\Taxonomy;
use Pollora\Taxonomy\Application\Services\TaxonomyService;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;

beforeEach(function () {
    $this->mockFactory = mock(TaxonomyFactoryInterface::class);
    $this->mockTaxonomy = mock(Taxonomy::class);
    $this->taxonomyService = new TaxonomyService($this->mockFactory);
});

test('register calls make on factory', function () {
    // Define test values
    $slug = 'test-taxonomy';
    $objectType = 'post';
    $singular = 'Test Taxonomy';
    $plural = 'Test Taxonomies';
    
    // Configure the mock
    $this->mockFactory
        ->shouldReceive('make')
        ->with($slug, $objectType, $singular, $plural)
        ->once()
        ->andReturn($this->mockTaxonomy);
    
    // Call the method under test
    $result = $this->taxonomyService->register($slug, $objectType, $singular, $plural);
    
    // Assert the result
    expect($result)->toBe($this->mockTaxonomy);
});

test('exists calls exists on factory', function () {
    // Define test values
    $taxonomy = 'test-taxonomy';
    
    // Configure the mock
    $this->mockFactory
        ->shouldReceive('exists')
        ->with($taxonomy)
        ->once()
        ->andReturn(true);
    
    // Call the method under test
    $result = $this->taxonomyService->exists($taxonomy);
    
    // Assert the result
    expect($result)->toBeTrue();
});

test('getRegistered calls getRegistered on factory', function () {
    // Define test values
    $registeredTaxonomies = ['category', 'tag', 'test-taxonomy'];
    
    // Configure the mock
    $this->mockFactory
        ->shouldReceive('getRegistered')
        ->once()
        ->andReturn($registeredTaxonomies);
    
    // Call the method under test
    $result = $this->taxonomyService->getRegistered();
    
    // Assert the result
    expect($result)->toBe($registeredTaxonomies);
}); 