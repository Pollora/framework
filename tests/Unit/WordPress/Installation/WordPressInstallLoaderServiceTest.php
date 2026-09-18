<?php

declare(strict_types=1);

use Pollora\Services\WordPress\Installation\WordPressInstallLoaderService;

/**
 * The install bootstrap rebuilds a minimal WordPress by hand, so anything
 * wp_install() reaches has to be on its list. It reaches the HTTP stack:
 * wp_install_maybe_enable_pretty_permalinks() calls wp_remote_get() on the site
 * URL, and WP_Http builds a WP_Http_Cookie for every Set-Cookie header of the
 * answer. Loading only part of the stack killed the install with
 * `Class "WP_Http_Cookie" not found` whenever something already answered at
 * that URL — a reinstall, a migration, a domain taken back.
 */
function installLoaderClasses(): array
{
    $constant = new ReflectionClassConstant(WordPressInstallLoaderService::class, 'CORE_CLASSES');

    /** @var array<int, string> $classes */
    $classes = $constant->getValue();

    return $classes;
}

describe('WordPressInstallLoaderService', function (): void {
    it('loads the HTTP stack wp-settings.php loads', function (): void {
        // wp-settings.php, in this order.
        $http = [
            '/class-wp-http.php',
            '/class-wp-http-streams.php',
            '/class-wp-http-curl.php',
            '/class-wp-http-proxy.php',
            '/class-wp-http-cookie.php',
            '/class-wp-http-encoding.php',
            '/class-wp-http-response.php',
            '/class-wp-http-requests-response.php',
            '/class-wp-http-requests-hooks.php',
        ];

        $loaded = array_values(array_filter(
            installLoaderClasses(),
            fn (string $class): bool => str_starts_with($class, '/class-wp-http')
        ));

        expect($loaded)->toBe($http);
    });

    it('lists every class file once', function (): void {
        $classes = installLoaderClasses();

        expect($classes)->toBe(array_values(array_unique($classes)));
    });
});
