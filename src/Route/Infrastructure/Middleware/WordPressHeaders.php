<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware to manage HTTP headers for WordPress responses.
 *
 * Applies three header transformations in sequence:
 * 1. Adds the `X-Powered-By: Pollora` framework identification header
 * 2. Strips WordPress-generated headers (Cache-Control, Expires, Content-Type)
 *    for non-WordPress routes served to anonymous visitors
 * 3. Sets public cache directives for non-authenticated visitors, using the
 *    `wordpress.cache.max_age` configuration value (default: 3600 seconds)
 *
 * Header cleanup targets routes without a WordPress condition (i.e. pure Laravel routes
 * that pass through the WordPress middleware stack), preventing WordPress from imposing
 * `no-cache` defaults on content it doesn't own.
 *
 * @see \Pollora\Route\Infrastructure\Providers\RouteServiceProvider::WORDPRESS_MIDDLEWARE
 */
class WordPressHeaders
{
    /**
     * The framework name used in the X-Powered-By header.
     */
    private const string FRAMEWORK_NAME = 'Pollora';

    /**
     * The HTTP header name for framework identification.
     */
    private const string FRAMEWORK_HEADER = 'X-Powered-By';

    /**
     * Handle the incoming request.
     *
     * Ensures the response is a Symfony Response instance, then applies
     * framework headers, WordPress header cleanup, and public cache directives.
     * Non-Symfony responses are wrapped in a new SymfonyResponse.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware handler in the pipeline
     * @return SymfonyResponse The response with adjusted headers
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        /** @var SymfonyResponse $response */
        $response = $next($request);

        if (! $response instanceof SymfonyResponse) {
            return new SymfonyResponse((string) $response);
        }

        $this->addFrameworkHeader($response);

        if ($this->shouldCleanupHeaders($request)) {
            $this->removeWordPressHeaders($response);
        }

        if ($this->shouldSetPublicCache()) {
            $this->applyPublicCacheHeaders($response);
        }

        return $response;
    }

    /**
     * Add the framework identification header to the response.
     *
     * Sets `X-Powered-By: Pollora` to identify responses served through
     * the Pollora framework, replacing any existing X-Powered-By value.
     *
     * @param  SymfonyResponse  $response  The response to modify
     */
    private function addFrameworkHeader(SymfonyResponse $response): void
    {
        $response->headers->set(self::FRAMEWORK_HEADER, self::FRAMEWORK_NAME);
    }

    /**
     * Determine if WordPress-generated headers should be removed.
     *
     * Returns true when all of the following conditions are met:
     * - The route exists and exposes the `hasCondition()` method
     * - The route has no WordPress condition (pure Laravel route)
     * - The visitor is not authenticated (WordPress `is_user_logged_in()` returns false)
     *
     * Authenticated users keep WordPress headers to preserve admin bar behavior
     * and prevent caching of personalized content.
     *
     * @param  Request  $request  The current HTTP request
     * @return bool True if WordPress headers should be stripped
     */
    private function shouldCleanupHeaders(Request $request): bool
    {
        $route = $request->route();

        return $route &&
               method_exists($route, 'hasCondition') &&
               ! $route->hasCondition() &&
               function_exists('is_user_logged_in') &&
               ! is_user_logged_in();
    }

    /**
     * Remove WordPress-generated headers from the response.
     *
     * Strips Cache-Control, Expires, and Content-Type headers that WordPress
     * sets via `nocache_headers()` and `send_headers()`. These are replaced
     * later by Pollora's own cache directives and Laravel's content type handling.
     *
     * @param  SymfonyResponse  $response  The response to clean up
     */
    private function removeWordPressHeaders(SymfonyResponse $response): void
    {
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Expires');
        $response->headers->remove('Content-Type');
    }

    /**
     * Determine if public cache headers should be applied.
     *
     * Returns true for anonymous visitors only. Authenticated users receive
     * WordPress's default no-cache behavior to prevent shared caches from
     * serving personalized content (admin bar, user-specific data).
     *
     * @return bool True if the visitor is not logged in
     */
    private function shouldSetPublicCache(): bool
    {
        return function_exists('is_user_logged_in') && ! is_user_logged_in();
    }

    /**
     * Apply public cache control headers for anonymous visitors.
     *
     * Replaces any existing Cache-Control header with public caching directives:
     * - `public` — allows shared caches (CDN, reverse proxy) to store the response
     * - `must-revalidate` — ensures caches check freshness before serving stale content
     * - `max-age` — TTL in seconds from `wordpress.cache.max_age` config (default: 3600)
     *
     * @param  SymfonyResponse  $response  The response to configure for caching
     */
    private function applyPublicCacheHeaders(SymfonyResponse $response): void
    {
        $response->headers->remove('Cache-Control');
        $response->setPublic();
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective(
            'max-age',
            (string) config('wordpress.cache.max_age', 3600)
        );
    }
}
