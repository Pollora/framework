<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Domain\Contracts;

use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

/**
 * Interface for template renderer components.
 */
interface TemplateRendererInterface
{
    /**
     * Resolve a template candidate into a renderable format.
     *
     * @param  TemplateCandidate  $candidate  The template candidate to resolve
     * @return string|null The resolved template path/identifier or null if not resolvable
     */
    public function resolve(TemplateCandidate $candidate): ?string;

    /**
     * Check if this renderer supports the given template type.
     *
     * @param  string  $type  The template type to check
     * @return bool Whether this renderer supports the given type
     */
    public function supports(string $type): bool;
}
