<?php

declare(strict_types=1);

namespace Pollora\Permalink\Domain\Contracts;

/**
 * Contract for URL normalization operations.
 *
 * Defines the interface for transforming URLs into their canonical form,
 * ensuring consistency across all generated links in the application.
 * Implementations handle edge cases such as root paths, query parameters,
 * and malformed URLs.
 */
interface UrlNormalizerInterface
{
    /**
     * Remove the trailing slash from a URL path.
     *
     * Strips the trailing slash from the path component of a URL while
     * preserving the scheme, host, port, query string, and fragment.
     * The root path `/` must be preserved as-is (never returned as empty string).
     *
     * @param  string|null  $url  The URL to normalize, or null
     * @return string|null The normalized URL without trailing slash, or null if input is null
     */
    public function removeTrailingSlash(?string $url): ?string;
}
