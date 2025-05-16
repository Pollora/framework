<?php

declare(strict_types=1);

use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;
use Pollora\TemplateHierarchy\Infrastructure\Services\AbstractTemplateResolver;

// Test implementation of AbstractTemplateResolver to access protected methods
class TestTemplateResolver extends AbstractTemplateResolver
{
    public function __construct()
    {
        $this->origin = 'test-origin';
    }

    public function applies(): bool
    {
        return true;
    }

    public function getCandidates(): array
    {
        return [];
    }

    // Public methods to expose protected methods for testing
    public function publicCreatePhpCandidate(string $templatePath, int $priority = 10): TemplateCandidate
    {
        return $this->createPhpCandidate($templatePath, $priority);
    }

    public function publicCreateBladeCandidate(string $templateName, int $priority = 10): TemplateCandidate
    {
        return $this->createBladeCandidate($templateName, $priority);
    }

    public function publicCreatePhpAndBladeCandidates(string $templatePath, int $priority = 10): array
    {
        return $this->createPhpAndBladeCandidates($templatePath, $priority);
    }

    public function publicGetQueriedObject(): ?object
    {
        return $this->getQueriedObject();
    }
}

beforeEach(function () {
    $this->resolver = new TestTemplateResolver;
});

test('it creates php candidate', function () {
    $candidate = $this->resolver->publicCreatePhpCandidate('template.php');

    expect($candidate)
        ->toBeInstanceOf(TemplateCandidate::class)
        ->and($candidate->type)->toBe('php')
        ->and($candidate->templatePath)->toBe('template.php')
        ->and($candidate->origin)->toBe('test-origin')
        ->and($candidate->priority)->toBe(10);
});

test('it creates php candidate with custom priority', function () {
    $candidate = $this->resolver->publicCreatePhpCandidate('template.php', 5);

    expect($candidate->priority)->toBe(5);
});

test('it creates blade candidate', function () {
    $candidate = $this->resolver->publicCreateBladeCandidate('blade.view');

    expect($candidate)
        ->toBeInstanceOf(TemplateCandidate::class)
        ->and($candidate->type)->toBe('blade')
        ->and($candidate->templatePath)->toBe('blade.view')
        ->and($candidate->origin)->toBe('test-origin')
        ->and($candidate->priority)->toBe(10);
});

test('it creates php and blade candidates', function () {
    $candidates = $this->resolver->publicCreatePhpAndBladeCandidates('path/to/template.php');

    expect($candidates)->toHaveCount(2)
        // First should be PHP
        ->and($candidates[0]->type)->toBe('php')
        ->and($candidates[0]->templatePath)->toBe('path/to/template.php')
        ->and($candidates[0]->priority)->toBe(10)
        // Second should be Blade
        ->and($candidates[1]->type)->toBe('blade')
        ->and($candidates[1]->templatePath)->toBe('path.to.template')
        ->and($candidates[1]->priority)->toBe(11); // PHP priority + 1
});

test('it converts directory separator to dots for blade', function () {
    $candidates = $this->resolver->publicCreatePhpAndBladeCandidates('parent/child/template.php');

    expect($candidates[1]->templatePath)->toBe('parent.child.template');
});

test('it gets queried object', function () {
    // Setup WordPress mocks
    setupWordPressMocks();

    // This requires WP functions, which should now be mocked
    $object = $this->resolver->publicGetQueriedObject();

    // In our test environment with mocks, we should get the mock object
    expect($object)->not->toBeNull();
});
