<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Services;

use Illuminate\Contracts\View\Factory;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateRendererInterface;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

/**
 * Renderer for Blade templates.
 */
class BladeTemplateRenderer implements TemplateRendererInterface
{
    /**
     * Create a new Blade template renderer.
     */
    public function __construct(
        private readonly Factory $viewFactory
    ) {}

    /**
     * Check if this renderer supports the given template type.
     */
    public function supports(string $type): bool
    {
        return $type === 'blade';
    }

    /**
     * Resolve a template candidate into a renderable format.
     *
     * @param  TemplateCandidate  $candidate  The template candidate to resolve
     * @return string|null The resolved blade view name or null if not found
     */
    public function resolve(TemplateCandidate $candidate): ?string
    {
        if (! $this->supports($candidate->type)) {
            return null;
        }

        // Check if the view exists
        if ($this->viewFactory->exists($candidate->templatePath)) {
            return $candidate->templatePath;
        }

        return null;
    }
}
