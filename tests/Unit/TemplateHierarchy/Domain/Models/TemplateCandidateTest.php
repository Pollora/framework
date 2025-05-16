<?php

declare(strict_types=1);

use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

test('it can be instantiated', function () {
    $candidate = new TemplateCandidate('php', 'path/to/template.php', 'wordpress');

    expect($candidate)
        ->toBeInstanceOf(TemplateCandidate::class)
        ->and($candidate->type)->toBe('php')
        ->and($candidate->templatePath)->toBe('path/to/template.php')
        ->and($candidate->origin)->toBe('wordpress')
        ->and($candidate->priority)->toBe(10); // Default priority
});

test('it can be instantiated with custom priority', function () {
    $candidate = new TemplateCandidate('php', 'path/to/template.php', 'wordpress', 5);

    expect($candidate->priority)->toBe(5);
});

test('it can be cloned with different priority', function () {
    $candidate = new TemplateCandidate('php', 'path/to/template.php', 'wordpress', 10);
    $cloned = $candidate->withPriority(5);

    expect($cloned->priority)->toBe(5)
        ->and($cloned->type)->toBe($candidate->type)
        ->and($cloned->templatePath)->toBe($candidate->templatePath)
        ->and($cloned->origin)->toBe($candidate->origin)
        ->and($cloned)->not->toBe($candidate);
});

test('it checks existence for php templates', function () {
    // Mock of a non-existent template
    $nonExistentTemplate = new TemplateCandidate('php', '/non/existent/path.php', 'wordpress');
    expect($nonExistentTemplate->exists())->toBeFalse();

    // Create a temporary file for testing
    $tempFile = tempnam(sys_get_temp_dir(), 'template-test');
    $existingTemplate = new TemplateCandidate('php', $tempFile, 'wordpress');
    expect($existingTemplate->exists())->toBeTrue();

    // Clean up
    unlink($tempFile);
});

test('blade templates always exist', function () {
    $bladeTemplate = new TemplateCandidate('blade', 'some.view.name', 'wordpress');
    expect($bladeTemplate->exists())->toBeTrue();
});
