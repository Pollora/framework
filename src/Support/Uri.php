<?php

declare(strict_types=1);

namespace Pollora\Support;

/**
 * Utility class for URI manipulation.
 *
 * This class provides methods to manipulate and format URIs,
 * particularly for handling slashes and rebuilding URLs from
 * their components.
 */
class Uri
{
    /**
     * Removes the trailing slash from a URL if present.
     *
     * @param  string|null  $url  The URL to process
     * @return string|null The URL without trailing slash or null if input is null
     */
    public function removeTrailingSlash(?string $url): ?string
    {
        if (empty($url)) {
            return $url;
        }

        $urlParts = parse_url($url);
        $urlParts['path'] = $urlParts['path'] ?? '';
        $urlParts['path'] = rtrim($urlParts['path'], '/');

        return $this->buildUrl($urlParts);
    }

    /**
     * Rebuilds a URL from its components.
     *
     * @param  array  $parts  The URL components (scheme, host, port, path, query, fragment)
     * @return string The rebuilt URL
     */
    protected function buildUrl(array $parts): string
    {
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? "?{$parts['query']}" : '';
        $fragment = isset($parts['fragment']) ? "#{$parts['fragment']}" : '';

        return $scheme
            ? "{$scheme}://{$host}{$port}{$path}{$query}{$fragment}"
            : "{$host}{$port}{$path}{$query}{$fragment}";
    }
}
