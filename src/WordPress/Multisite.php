<?php

declare(strict_types=1);

namespace Pollora\WordPress;

/**
 * What Pollora knows about a WordPress network, and the few guard rails around it.
 *
 * Multisite is not a supported mode: it is a bridge. A network works on the front,
 * on the REST API and in the main site's administration, and fails visibly
 * elsewhere. Nothing here runs on a single site.
 */
final class Multisite
{
    /**
     * Whether the project declares a network.
     *
     * The constant wins (wp-config.php or an earlier definition); the
     * `wordpress.constants` configuration is read too, because Bootstrap decides
     * about the home URL before that configuration has become constants.
     */
    public static function isConfigured(): bool
    {
        if (defined('MULTISITE')) {
            return (bool) constant('MULTISITE');
        }

        foreach ((array) config('wordpress.constants', []) as $key => $value) {
            if (strtoupper((string) $key) === 'MULTISITE') {
                return (bool) $value;
            }
        }

        return false;
    }

    /**
     * The home URL of the main site.
     *
     * Outside a network Bootstrap defines WP_HOME, so the `/cms` the installer
     * stored in the `home` option never shows. In a network WordPress removes
     * the filters that make a constant win over the option: `home_url()` and
     * the canonical redirect would send visitors to `/cms/…`. The stored value
     * is replaced only when it is exactly the WordPress directory under the
     * application URL; any other value was chosen by someone and is kept.
     */
    public static function mainSiteHome(mixed $stored, string $appUrl): mixed
    {
        if (! is_string($stored) || $appUrl === '') {
            return $stored;
        }

        $appUrl = rtrim($appUrl, '/');

        return rtrim($stored, '/') === $appUrl.'/cms' ? $appUrl : $stored;
    }

    /**
     * Tells whether the blog being served is the main site of its network.
     *
     * @param  array<string, mixed>  $globals  The WordPress globals ($GLOBALS)
     */
    public static function isMainSite(array $globals): bool
    {
        $site = $globals['current_site'] ?? null;

        if (! is_object($site) || ! isset($site->blog_id, $globals['blog_id'])) {
            return false;
        }

        return (int) $site->blog_id === (int) $globals['blog_id'];
    }

    /**
     * The `HTTP_HOST` a console command presents to WordPress.
     *
     * A network is matched on the host as stored in `wp_blogs`, port included
     * when the network domain has one: without the port WordPress finds no
     * site and redirects.
     */
    public static function consoleHost(string $appUrl): string
    {
        $host = AppUrl::host($appUrl);

        if ($host === null) {
            return 'localhost';
        }

        $port = parse_url(AppUrl::normalize($appUrl), PHP_URL_PORT);

        return is_int($port) ? $host.':'.$port : $host;
    }

    /**
     * The `REQUEST_URI` of a console command: the path of the application, or `/`.
     *
     * WordPress reads it without checking it exists to tell which site of the
     * network is asked for.
     */
    public static function consoleRequestUri(string $appUrl): string
    {
        $path = parse_url(AppUrl::normalize($appUrl), PHP_URL_PATH);

        $path = is_string($path) ? trim($path, '/') : '';

        return $path === '' ? '/' : '/'.$path.'/';
    }

    /**
     * Tells whether the response code of the process is a redirect.
     *
     * `headers_list()` is empty in the console, but `header('Location: …')` still
     * sets the status the process would answer with: that is what remains of
     * a redirect after WordPress has exited.
     */
    public static function isRedirectStatus(int|false $status): bool
    {
        return in_array($status, [301, 302, 303, 307, 308], true);
    }

    /**
     * The message that replaces WordPress's silent exit.
     */
    public static function redirectMessage(string $host, ?string $networkDomain): string
    {
        return sprintf(
            "Pollora: WordPress redirected this command (the host \"%s\" is not the one of the network%s) and stopped without a message.\n"
            ."APP_URL, DOMAIN_CURRENT_SITE and PATH_CURRENT_SITE must agree, port included.\n",
            $host,
            $networkDomain === null ? '' : ', "'.$networkDomain.'"',
        );
    }
}
