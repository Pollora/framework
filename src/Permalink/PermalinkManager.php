<?php

declare(strict_types=1);

namespace Pollora\Permalink;

use Pollora\Support\Uri;

/**
 * Manages permalink structure and canonical redirections.
 *
 * This class is responsible for handling WordPress permalink structures,
 * including their updates and validation. It also manages canonical
 * redirections to ensure URL consistency.
 */
class PermalinkManager
{
    /**
     * Updates the permalink structure after sanitization.
     *
     * @param  string|bool  $permalinkStructure  The new permalink structure to apply
     *
     * @throws \InvalidArgumentException If the structure is invalid
     */
    public function updateStructure(string|bool $permalinkStructure): void
    {
        if ($this->isValidPermalinkStructure($permalinkStructure)) {
            update_option('permalink_structure', $this->sanitizeStructure($permalinkStructure));
        }
    }

    /**
     * Checks if the provided permalink structure is valid.
     *
     * @param  mixed  $structure  The structure to validate
     * @return bool True if the structure is valid, false otherwise
     */
    protected function isValidPermalinkStructure(mixed $structure): bool
    {
        return ! in_array($structure, ['', '0', false], true);
    }

    /**
     * Cleans the permalink structure by removing trailing slashes.
     *
     * @param  string|bool  $structure  The structure to clean
     * @return string The cleaned structure
     */
    protected function sanitizeStructure(string|bool $structure): string
    {
        return is_string($structure) ? rtrim($structure, '/') : '';
    }

    /**
     * Handles canonical URL redirections.
     *
     * If the canonical URL corresponds to the homepage with a GET parameter,
     * the URL is returned as-is to avoid issues when removing the trailing slash.
     *
     * @param  string|null  $canonicalUrl  The canonical URL to process.
     * @return string|null The processed canonical URL.
     */
    public function handleCanonicalRedirect(?string $canonicalUrl): ?string
    {
        if ($canonicalUrl === null) {
            return null;
        }

        $homeUrl = home_url();
        $canonicalParts = parse_url($canonicalUrl);
        $homeParts = parse_url($homeUrl);

        // Ensure both URLs are on the same domain.
        if (
            isset($canonicalParts['host'], $homeParts['host']) &&
            $canonicalParts['host'] === $homeParts['host']
        ) {
            // Normalize paths with a default value of "/".
            $canonicalPath = $canonicalParts['path'] ?? '/';
            $homePath = $homeParts['path'] ?? '/';

            // Compare paths without considering the trailing slash.
            // If a GET parameter is present, return the original URL.
            if (rtrim($canonicalPath, '/') === rtrim($homePath, '/') && ! empty($canonicalParts['query'])) {
                return $canonicalUrl;
            }
        }

        return app(Uri::class)->removeTrailingSlash($canonicalUrl);
    }
}
