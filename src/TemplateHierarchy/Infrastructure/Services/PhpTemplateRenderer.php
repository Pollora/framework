<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Services;

use Pollora\TemplateHierarchy\Domain\Contracts\TemplateRendererInterface;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

/**
 * Renderer for PHP templates.
 */
class PhpTemplateRenderer implements TemplateRendererInterface
{
    /**
     * Create a new PHP template renderer.
     *
     * @param  string[]  $templatePaths  Array of paths to search for templates
     */
    public function __construct(
        private readonly array $templatePaths
    ) {}

    /**
     * Check if this renderer supports the given template type.
     */
    public function supports(string $type): bool
    {
        return $type === 'php';
    }

    /**
     * Resolve a template candidate into a renderable format.
     *
     * @param  TemplateCandidate  $candidate  The template candidate to resolve
     * @return string|null The resolved template path or null if not resolvable
     */
    public function resolve(TemplateCandidate $candidate): ?string
    {
        if (! $this->supports($candidate->type)) {
            return null;
        }

        // If the candidate has an absolute path, check if it exists
        if (file_exists($candidate->templatePath) && is_readable($candidate->templatePath)) {
            return $candidate->templatePath;
        }

        // Look for the template in the template paths
        foreach ($this->templatePaths as $basePath) {
            $fullPath = rtrim($basePath, '/\\').DIRECTORY_SEPARATOR.$candidate->templatePath;
            if (file_exists($fullPath) && is_readable($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }
}
