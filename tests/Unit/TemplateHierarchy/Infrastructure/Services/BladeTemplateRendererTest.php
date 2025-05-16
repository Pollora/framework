<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory;
use Mockery as m;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;
use Pollora\TemplateHierarchy\Infrastructure\Services\BladeTemplateRenderer;

beforeEach(function () {
    $this->viewFactory = m::mock(Factory::class);
    $this->renderer = new BladeTemplateRenderer($this->viewFactory);
});

afterEach(function () {
    m::close();
});

test('it supports blade type', function () {
    expect($this->renderer->supports('blade'))->toBeTrue()
        ->and($this->renderer->supports('php'))->toBeFalse()
        ->and($this->renderer->supports('other'))->toBeFalse();
});

test('it resolves existing blade view', function () {
    $viewName = 'theme.pages.home';
    $candidate = new TemplateCandidate('blade', $viewName, 'test');

    $this->viewFactory->shouldReceive('exists')
        ->with($viewName)
        ->once()
        ->andReturn(true);

    $resolvedView = $this->renderer->resolve($candidate);

    expect($resolvedView)->toBe($viewName);
});

test('it returns null for nonexistent blade view', function () {
    $viewName = 'theme.pages.nonexistent';
    $candidate = new TemplateCandidate('blade', $viewName, 'test');

    $this->viewFactory->shouldReceive('exists')
        ->with($viewName)
        ->once()
        ->andReturn(false);

    $resolvedView = $this->renderer->resolve($candidate);

    expect($resolvedView)->toBeNull();
});

test('it returns null for unsupported type', function () {
    $candidate = new TemplateCandidate('php', 'path/to/template.php', 'test');

    // View factory should not be called at all

    $resolvedView = $this->renderer->resolve($candidate);

    expect($resolvedView)->toBeNull();
});

test('it resolves blade view with dot notation', function () {
    $viewName = 'woocommerce.single-product';
    $candidate = new TemplateCandidate('blade', $viewName, 'woocommerce');

    $this->viewFactory->shouldReceive('exists')
        ->with($viewName)
        ->once()
        ->andReturn(true);

    $resolvedView = $this->renderer->resolve($candidate);

    expect($resolvedView)->toBe($viewName);
});
