<?php

declare(strict_types=1);

namespace Pollora\Permalink\Domain\Services;

use Pollora\Permalink\Domain\Contracts\UrlNormalizerInterface;
use Pollora\Support\Uri;

/**
 * URL normalizer that removes trailing slashes.
 *
 * Delegates URL manipulation to the {@see Uri} support class and provides
 * a domain-level guard for the root path edge case: the path `/` must
 * remain `/` and never become an empty string.
 *
 * This service is used by the permalink system to ensure all WordPress-generated
 * URLs (posts, pages, terms, archives, feeds, pagination) are consistent
 * with Pollora's no-trailing-slash convention.
 */
class UrlNormalizer implements UrlNormalizerInterface
{
    /**
     * Create a new URL normalizer instance.
     *
     * @param  Uri  $uri  The URI manipulation utility
     */
    public function __construct(
        private readonly Uri $uri
    ) {}

    /**
     * Remove the trailing slash from a URL path.
     *
     * Delegates to {@see Uri::removeTrailingSlash()} for the actual manipulation,
     * then guards against the root path edge case where `rtrim('/', '/')` would
     * produce an empty string.
     *
     * Examples:
     * - `/hello-world/` → `/hello-world`
     * - `/page/2/` → `/page/2`
     * - `/` → `/` (preserved)
     * - `https://example.com/` → `https://example.com`
     * - `null` → `null`
     *
     * @param  string|null  $url  The URL to normalize, or null
     * @return string|null The normalized URL, or null if input is null
     */
    public function removeTrailingSlash(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $normalized = $this->uri->removeTrailingSlash($url);

        // Guard: root path `/` must not become empty string
        if ($normalized === '' || $normalized === '0') {
            return '/';
        }

        return $normalized;
    }
}
