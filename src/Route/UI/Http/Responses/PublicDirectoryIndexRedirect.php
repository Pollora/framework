<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Send a request for a `public/` directory to the `index.php` inside it.
 *
 * A web server with a document root answers `/cms/wp-admin/` by running that
 * directory's index.php. Valet and Herd hand every URL that is not a file to
 * the front controller instead, so the request reached the WordPress fallback
 * and answered 404 — on the very address `pollora:install` prints for the
 * admin. The redirect reaches the same file explicitly, which every server
 * serves.
 */
final class PublicDirectoryIndexRedirect
{
    public static function for(Request $request, string $publicPath): ?RedirectResponse
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }

        $path = rawurldecode($request->getPathInfo());

        if ($path === '/' || ! str_ends_with($path, '/') || str_contains($path, '..')) {
            return null;
        }

        // The content folders answer the site's 404 on purpose (see the
        // skeleton's .htaccess): redirecting would tell a visitor they exist
        if (preg_match('#^/(cms/wp-content|content)(/|$)#', $path) === 1) {
            return null;
        }

        $root = realpath($publicPath);
        $directory = realpath($publicPath.$path);

        if ($root === false || $directory === false || ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! is_file($directory.DIRECTORY_SEPARATOR.'index.php')) {
            return null;
        }

        $query = $request->getQueryString();

        return new RedirectResponse($request->getBaseUrl().$request->getPathInfo().'index.php'.($query !== null && $query !== '' ? '?'.$query : ''));
    }
}
