<?php

declare(strict_types=1);

use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;
use Pollora\TemplateHierarchy\Infrastructure\Services\PhpTemplateRenderer;

beforeEach(function () {
    // Create temporary directory for test templates
    $this->tempDir = sys_get_temp_dir().'/template-tests-'.uniqid();
    mkdir($this->tempDir);

    // Create renderer with the temporary directory in path
    $this->renderer = new PhpTemplateRenderer([$this->tempDir]);
});

afterEach(function () {
    // Clean up temporary directory
    if (is_dir($this->tempDir)) {
        foreach (glob($this->tempDir.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }
});

test('it supports php type', function () {
    expect($this->renderer->supports('php'))->toBeTrue()
        ->and($this->renderer->supports('blade'))->toBeFalse()
        ->and($this->renderer->supports('other'))->toBeFalse();
});

test('it resolves absolute path', function () {
    // Create a temporary file
    $tempFile = $this->tempDir.'/absolute-test.php';
    file_put_contents($tempFile, '<?php echo "test"; ?>');

    $candidate = new TemplateCandidate('php', $tempFile, 'test');

    $resolvedPath = $this->renderer->resolve($candidate);

    expect($resolvedPath)->toBe($tempFile);
});

test('it resolves relative path', function () {
    // Create a template in the search path
    $tempFile = 'relative-test.php';
    $fullPath = $this->tempDir.'/'.$tempFile;
    file_put_contents($fullPath, '<?php echo "test"; ?>');

    $candidate = new TemplateCandidate('php', $tempFile, 'test');

    $resolvedPath = $this->renderer->resolve($candidate);

    expect($resolvedPath)->toBe($fullPath);
});

test('it returns null for nonexistent template', function () {
    $candidate = new TemplateCandidate('php', 'nonexistent-template.php', 'test');

    $resolvedPath = $this->renderer->resolve($candidate);

    expect($resolvedPath)->toBeNull();
});

test('it returns null for unsupported type', function () {
    // Create a template file
    $tempFile = $this->tempDir.'/blade-test.php';
    file_put_contents($tempFile, '<?php echo "test"; ?>');

    $candidate = new TemplateCandidate('blade', $tempFile, 'test');

    $resolvedPath = $this->renderer->resolve($candidate);

    expect($resolvedPath)->toBeNull();
});

test('it searches multiple template paths', function () {
    // Create a second temporary directory
    $secondTempDir = sys_get_temp_dir().'/template-tests-2-'.uniqid();
    mkdir($secondTempDir);

    try {
        // Create a template in the second directory
        $tempFile = 'multi-path-test.php';
        $fullPath = $secondTempDir.'/'.$tempFile;
        file_put_contents($fullPath, '<?php echo "test"; ?>');

        // Create renderer with multiple paths
        $multiPathRenderer = new PhpTemplateRenderer([$this->tempDir, $secondTempDir]);

        $candidate = new TemplateCandidate('php', $tempFile, 'test');

        $resolvedPath = $multiPathRenderer->resolve($candidate);

        expect($resolvedPath)->toBe($fullPath);
    } finally {
        // Clean up
        if (file_exists($secondTempDir.'/'.$tempFile)) {
            unlink($secondTempDir.'/'.$tempFile);
        }
        if (is_dir($secondTempDir)) {
            rmdir($secondTempDir);
        }
    }
});
