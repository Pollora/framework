<?php

declare(strict_types=1);

namespace Pollora\Permalink\Infrastructure\Services;

use Pollora\Permalink\Domain\Contracts\UrlNormalizerInterface;
use Pollora\Permalink\Infrastructure\Providers\PermalinkServiceProvider;

/**
 * Manages permalink structure and URL normalization for WordPress.
 *
 * This service acts as the bridge between WordPress's permalink system and
 * Pollora's no-trailing-slash convention. It handles three concerns:
 *
 * 1. **URL generation** — Intercepts the `user_trailingslashit` filter to remove
 *    trailing slashes from all WordPress-generated URLs at the source (posts,
 *    pages, terms, archives, feeds, pagination).
 *
 * 2. **Canonical redirects** — Intercepts the `redirect_canonical` filter to
 *    ensure visitors accessing URLs with trailing slashes are redirected to
 *    the normalized version (301).
 *
 * 3. **Permalink structure storage** — Ensures the permalink structure option
 *    is stored without a trailing slash, so `WP_Rewrite::$use_trailing_slashes`
 *    evaluates to `false`.
 *
 * @see PermalinkServiceProvider
 */
class PermalinkManager
{
    /**
     * Create a new permalink manager instance.
     *
     * @param  UrlNormalizerInterface  $normalizer  The URL normalizer for trailing slash removal
     */
    public function __construct(
        private readonly UrlNormalizerInterface $normalizer
    ) {}

    /**
     * Remove the trailing slash from a WordPress-generated URL.
     *
     * Hooked to the `user_trailingslashit` filter, which WordPress calls for
     * every URL it generates through its link template functions. This is the
     * most upstream interception point, ensuring all links in templates, menus,
     * sitemaps, and REST responses are consistent.
     *
     * WordPress URL types passed as `$type`:
     * `single`, `page`, `single_paged`, `category`, `post_tag`, `author`,
     * `year`, `month`, `day`, `paged`, `commentpaged`, `feed`, etc.
     *
     * @param  string  $url  The URL after WordPress's trailing slash processing
     * @param  string  $type  The URL type identifier (e.g. 'single', 'page', 'feed')
     * @return string The URL without trailing slash
     */
    public function handleUserTrailingSlash(string $url, string $type): string
    {
        return $this->normalizer->removeTrailingSlash($url) ?? $url;
    }

    /**
     * Normalize the canonical redirect URL by removing trailing slashes.
     *
     * Hooked to the `redirect_canonical` filter as a safety net for direct URL
     * access from external sources (bookmarks, search engines, backlinks) that
     * may include trailing slashes. WordPress fires this filter before issuing
     * a 301 redirect to the canonical URL.
     *
     * Preserves the original URL (with trailing slash) when the canonical URL
     * points to the homepage with query parameters, to avoid breaking URLs
     * like `https://example.com/?author=1`.
     *
     * @param  string|null  $canonicalUrl  The canonical URL computed by WordPress, or null to cancel redirect
     * @return string|null The normalized canonical URL, or null if input is null
     */
    public function handleCanonicalRedirect(?string $canonicalUrl): ?string
    {
        if ($canonicalUrl === null) {
            return null;
        }

        if ($this->isHomepageWithQuery($canonicalUrl)) {
            return $canonicalUrl;
        }

        return $this->normalizer->removeTrailingSlash($canonicalUrl);
    }

    /**
     * Update the permalink structure after sanitization.
     *
     * Hooked to the `permalink_structure_changed` action. Ensures the stored
     * permalink structure never ends with a trailing slash, which causes
     * `WP_Rewrite::$use_trailing_slashes` to evaluate to `false`.
     *
     * @param  string|bool  $permalinkStructure  The new permalink structure to apply
     */
    public function updateStructure(string|bool $permalinkStructure): void
    {
        if ($this->isValidPermalinkStructure($permalinkStructure)) {
            update_option('permalink_structure', $this->sanitizeStructure($permalinkStructure));
        }
    }

    /**
     * Check if the URL is the homepage with query parameters.
     *
     * Compares the URL's host and path against the site's home URL. If they
     * match and query parameters are present, the URL should be preserved as-is
     * to avoid stripping the trailing slash from `https://example.com/?param=1`.
     *
     * @param  string  $url  The URL to check
     * @return bool True if the URL is the homepage with query parameters
     */
    private function isHomepageWithQuery(string $url): bool
    {
        $homeUrl = home_url();
        $canonicalParts = parse_url($url);
        $homeParts = parse_url($homeUrl);

        if (
            ! isset($canonicalParts['host'], $homeParts['host']) ||
            $canonicalParts['host'] !== $homeParts['host']
        ) {
            return false;
        }

        $canonicalPath = $canonicalParts['path'] ?? '/';
        $homePath = $homeParts['path'] ?? '/';

        return rtrim($canonicalPath, '/') === rtrim($homePath, '/')
            && ! empty($canonicalParts['query']);
    }

    /**
     * Check if the provided permalink structure is valid.
     *
     * Rejects empty strings, '0', and false values which would indicate
     * no permalink structure is set (plain permalinks).
     *
     * @param  mixed  $structure  The structure to validate
     * @return bool True if the structure is valid
     */
    private function isValidPermalinkStructure(mixed $structure): bool
    {
        return ! in_array($structure, ['', '0', false], true);
    }

    /**
     * Sanitize the permalink structure by removing the trailing slash.
     *
     * This ensures `WP_Rewrite::$use_trailing_slashes` evaluates to `false`,
     * which is derived from `str_ends_with(permalink_structure, '/')`.
     *
     * @param  string|bool  $structure  The structure to sanitize
     * @return string The sanitized structure without trailing slash
     */
    private function sanitizeStructure(string|bool $structure): string
    {
        return is_string($structure) ? rtrim($structure, '/') : '';
    }
}
