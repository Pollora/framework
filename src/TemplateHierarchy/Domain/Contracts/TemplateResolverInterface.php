<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Domain\Contracts;

use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

/**
 * Interface for template resolver components.
 */
interface TemplateResolverInterface
{
    /**
     * Return a list of possible template candidates for the current request.
     *
     * @return TemplateCandidate[]
     */
    public function getCandidates(): array;

    /**
     * Check if this resolver applies to the current request context.
     */
    public function applies(): bool;
}
