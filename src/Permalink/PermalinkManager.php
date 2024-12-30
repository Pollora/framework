<?php

declare(strict_types=1);

namespace Pollora\Permalink;
use Illuminate\Routing\UrlGenerator;

class PermalinkManager
{
    /**
     * Sanitize and update the permalink structure.
     */
    public function updateStructure(string|bool $permalinkStructure): void
    {
        if ($this->isValidPermalinkStructure($permalinkStructure)) {
            update_option('permalink_structure', $this->sanitizeStructure($permalinkStructure));
        }
    }

    /**
     * Check if the permalink structure is valid.
     */
    protected function isValidPermalinkStructure(mixed $structure): bool
    {
        return !in_array($structure, ['', '0', false], true);
    }

    /**
     * Sanitize the permalink structure by removing trailing slashes.
     */
    protected function sanitizeStructure(string|bool $structure): string
    {
        return is_string($structure) ? rtrim($structure, '/') : '';
    }

    /**
     * Handle canonical URL redirection.
     */
    public function handleCanonicalRedirect(?string $canonicalUrl): ?string
    {
        return app(UrlGenerator::class)->removeTrailingSlash($canonicalUrl);
    }
}
