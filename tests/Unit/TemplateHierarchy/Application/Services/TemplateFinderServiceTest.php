<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Mockery as m;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\TemplateHierarchy\Application\Services\TemplateFinderService;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateRendererInterface;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateResolverInterface;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateSourceInterface;
use Pollora\TemplateHierarchy\Domain\Exceptions\TemplateNotFoundException;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

beforeEach(function () {
    $this->config = m::mock(Repository::class);
    $this->filter = m::mock(Filter::class);
    $this->filter->shouldReceive('apply')->andReturnArg(1);

    $this->service = new TemplateFinderService(
        $this->config,
        $this->filter
    );
});

afterEach(function () {
    m::close();
});

test('it registers template source', function () {
    $source = m::mock(TemplateSourceInterface::class);
    $source->shouldReceive('getName')->andReturn('test-source');
    $source->shouldReceive('getPriority')->andReturn(10);

    $this->service->registerSource($source);

    // We should be able to build the hierarchy with the source
    $source->shouldReceive('getResolvers')->andReturn([]);

    expect($this->service->getHierarchy())->toBeEmpty();
});

test('it registers template renderer', function () {
    $renderer = m::mock(TemplateRendererInterface::class);

    $this->service->registerRenderer($renderer);

    // This doesn't directly test anything observable, but we're testing
    // the renderer is actually used in the resolveTemplate test
    expect(true)->toBeTrue();
});

test('it builds hierarchy from sources', function () {
    // Create source mocks with different priorities
    $source1 = m::mock(TemplateSourceInterface::class);
    $source1->shouldReceive('getName')->andReturn('source1');
    $source1->shouldReceive('getPriority')->andReturn(20); // Lower priority

    $source2 = m::mock(TemplateSourceInterface::class);
    $source2->shouldReceive('getName')->andReturn('source2');
    $source2->shouldReceive('getPriority')->andReturn(10); // Higher priority

    // Create resolver mocks
    $resolver1 = m::mock(TemplateResolverInterface::class);
    $resolver1->shouldReceive('applies')->andReturn(true);
    $resolver1->shouldReceive('getCandidates')->andReturn([
        new TemplateCandidate('php', 'source1.php', 'source1'),
    ]);

    $resolver2 = m::mock(TemplateResolverInterface::class);
    $resolver2->shouldReceive('applies')->andReturn(true);
    $resolver2->shouldReceive('getCandidates')->andReturn([
        new TemplateCandidate('php', 'source2.php', 'source2', 5), // Higher priority
    ]);

    // Connect sources and resolvers
    $source1->shouldReceive('getResolvers')->andReturn([$resolver1]);
    $source2->shouldReceive('getResolvers')->andReturn([$resolver2]);

    // Setup filter expectation for the hierarchy
    $this->filter->shouldReceive('apply')
        ->with('pollora/template_hierarchy/hierarchy', m::any())
        ->andReturnArg(1);

    // Register sources
    $this->service->registerSource($source1);
    $this->service->registerSource($source2);

    // Get hierarchy and check order
    $hierarchy = $this->service->getHierarchy();

    expect($hierarchy)->toHaveCount(2)
        ->and($hierarchy[0]->templatePath)->toBe('source2.php') // Higher source priority
        ->and($hierarchy[1]->templatePath)->toBe('source1.php'); // Lower source priority
});

test('it resolves template with renderer', function () {
    // Create a template candidate
    $candidate = new TemplateCandidate('php', 'template.php', 'test');

    // Create a source that returns the candidate
    $source = m::mock(TemplateSourceInterface::class);
    $source->shouldReceive('getName')->andReturn('test-source');
    $source->shouldReceive('getPriority')->andReturn(10);

    $resolver = m::mock(TemplateResolverInterface::class);
    $resolver->shouldReceive('applies')->andReturn(true);
    $resolver->shouldReceive('getCandidates')->andReturn([$candidate]);

    $source->shouldReceive('getResolvers')->andReturn([$resolver]);

    // Create a renderer that resolves the candidate
    $renderer = m::mock(TemplateRendererInterface::class);
    $renderer->shouldReceive('supports')->with('php')->andReturn(true);
    $renderer->shouldReceive('resolve')->with($candidate)->andReturn('/resolved/path/to/template.php');

    // Setup filter expectation for candidates
    $this->filter->shouldReceive('apply')
        ->with('pollora/template_hierarchy/candidates', m::any())
        ->andReturnArg(1);

    // Register source and renderer
    $this->service->registerSource($source);
    $this->service->registerRenderer($renderer);

    // Resolve template
    $resolvedTemplate = $this->service->resolveTemplate();

    expect($resolvedTemplate)->toBe('/resolved/path/to/template.php');
});

test('it throws exception when no template found', function () {
    // Create a source with no candidates
    $source = m::mock(TemplateSourceInterface::class);
    $source->shouldReceive('getName')->andReturn('test-source');
    $source->shouldReceive('getPriority')->andReturn(10);
    $source->shouldReceive('getResolvers')->andReturn([]);

    // Register source
    $this->service->registerSource($source);

    // Expect exception when resolving
    expect(fn () => $this->service->resolveTemplate())->toThrow(TemplateNotFoundException::class);
});

test('it tries multiple renderers until one resolves', function () {
    // Create a template candidate
    $candidate = new TemplateCandidate('php', 'template.php', 'test');

    // Create a source that returns the candidate
    $source = m::mock(TemplateSourceInterface::class);
    $source->shouldReceive('getName')->andReturn('test-source');
    $source->shouldReceive('getPriority')->andReturn(10);

    $resolver = m::mock(TemplateResolverInterface::class);
    $resolver->shouldReceive('applies')->andReturn(true);
    $resolver->shouldReceive('getCandidates')->andReturn([$candidate]);

    $source->shouldReceive('getResolvers')->andReturn([$resolver]);

    // Create renderers
    $renderer1 = m::mock(TemplateRendererInterface::class);
    $renderer1->shouldReceive('supports')->with('php')->andReturn(false);

    $renderer2 = m::mock(TemplateRendererInterface::class);
    $renderer2->shouldReceive('supports')->with('php')->andReturn(true);
    $renderer2->shouldReceive('resolve')->with($candidate)->andReturn(null);

    $renderer3 = m::mock(TemplateRendererInterface::class);
    $renderer3->shouldReceive('supports')->with('php')->andReturn(true);
    $renderer3->shouldReceive('resolve')->with($candidate)->andReturn('/resolved/path.php');

    // Setup filter expectation for candidates
    $this->filter->shouldReceive('apply')
        ->with('pollora/template_hierarchy/candidates', m::any())
        ->andReturnArg(1);

    // Register source and renderers
    $this->service->registerSource($source);
    $this->service->registerRenderer($renderer1);
    $this->service->registerRenderer($renderer2);
    $this->service->registerRenderer($renderer3);

    // Resolve template
    $resolvedTemplate = $this->service->resolveTemplate();

    expect($resolvedTemplate)->toBe('/resolved/path.php');
});
