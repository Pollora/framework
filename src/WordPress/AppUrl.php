<?php

declare(strict_types=1);

namespace Pollora\WordPress;

/**
 * The application's URL, as WordPress needs it.
 *
 * `APP_URL=pollora.test` is a mistake a hand-edited .env lets through. Read as
 * it is, WordPress builds every URL relative to the current path and the
 * console cannot find the host. A value without a scheme is read as http://,
 * the scheme of a local site that was not secured.
 */
final class AppUrl
{
    public static function normalize(string $url): string
    {
        $url = trim($url);

        if ($url === '' || self::hasScheme($url)) {
            return $url;
        }

        return 'http://'.ltrim($url, '/');
    }

    public static function hasScheme(string $url): bool
    {
        return preg_match('#^[a-z][a-z0-9+.-]*://#i', trim($url)) === 1;
    }

    /**
     * The host of the URL, without its port; null when there is none.
     */
    public static function host(string $url): ?string
    {
        $host = parse_url(self::normalize($url), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
