<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Middleware to manage HTTP headers for WordPress responses.
 *
 * Applies header transformations in sequence:
 * 1. Adds the `X-Powered-By: Pollora` framework identification header
 * 2. Strips WordPress-generated cache headers (Cache-Control, Expires)
 *    for non-WordPress routes served to anonymous visitors
 * 3. Sets public cache directives for cacheable HTML responses served
 *    to non-authenticated visitors, using the `wordpress.cache.max_age`
 *    configuration value (default: 3600 seconds)
 *
 * Header cleanup targets routes without a WordPress condition (i.e. pure Laravel
 * routes that pass through the WordPress middleware stack), preventing WordPress
 * from imposing `no-cache` defaults on content it doesn't own.
 *
 * The middleware respects application-level headers: Content-Type is never modified,
 * and explicit `no-store` cache restrictions set by controllers or plugins are
 * preserved. Non-HTML responses (JSON, PDF, binary downloads) are left untouched
 * by the cache logic.
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

        // Capture application-level cache restriction BEFORE cleanup,
        // since removeWordPressHeaders resets Cache-Control and Symfony
        // regenerates a default `no-cache, private` that is not intentional.
        $hasExplicitRestriction = $this->hasCacheRestriction($response);

        if ($this->shouldCleanupHeaders($request)) {
            $this->removeWordPressHeaders($response);
            // WordPress nocache headers were the restriction — not application intent
            $hasExplicitRestriction = false;
        }

        if (! $hasExplicitRestriction && $this->shouldApplyPublicCache($response)) {
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
     * Remove WordPress-generated cache headers from the response.
     *
     * Strips only Cache-Control and Expires headers that WordPress sets via
     * `nocache_headers()` and `WP::send_headers()`. Content-Type is intentionally
     * preserved as it belongs to the Symfony Response (set by Laravel or the
     * response type), not to WordPress's header pollution.
     *
     * @param  SymfonyResponse  $response  The response to clean up
     */
    private function removeWordPressHeaders(SymfonyResponse $response): void
    {
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Expires');
    }

    /**
     * Determine if public cache headers should be applied to this response.
     *
     * Returns true only when all of the following conditions are met:
     * - WordPress functions are available
     * - The visitor is not authenticated (anonymous)
     * - The response is a standard content response (not streamed, binary, redirect, or empty)
     * - The response has an HTML content type (non-HTML responses like JSON, PDF, XML are skipped)
     *
     * @param  SymfonyResponse  $response  The response to evaluate
     * @return bool True if public cache directives should be applied
     */
    private function shouldApplyPublicCache(SymfonyResponse $response): bool
    {
        if (! function_exists('is_user_logged_in') || is_user_logged_in()) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if ($response->isRedirection() || $response->isInformational() || $response->isEmpty()) {
            return false;
        }

        return $this->isHtmlResponse($response);
    }

    /**
     * Determine if the response has an HTML content type.
     *
     * A response is considered HTML if its Content-Type header contains "text/html"
     * or if no Content-Type has been set (default assumption for web responses).
     *
     * @param  SymfonyResponse  $response  The response to check
     * @return bool True if the response is HTML
     */
    private function isHtmlResponse(SymfonyResponse $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains((string) $contentType, 'text/html');
    }

    /**
     * Check if the response has an explicit cache restriction set by application code.
     *
     * Detects the `no-store` directive in the Cache-Control header, which indicates
     * that a controller, plugin, or middleware has explicitly declared that this
     * response must not be cached at all. This directive is respected and not overridden.
     *
     * Note: `private` is not checked because Symfony's ResponseHeaderBag always
     * includes it as a default on responses without explicit Cache-Control, making
     * it unreliable as an indicator of application intent.
     *
     * @param  SymfonyResponse  $response  The response to inspect
     * @return bool True if a no-store directive is present
     */
    private function hasCacheRestriction(SymfonyResponse $response): bool
    {
        return $response->headers->hasCacheControlDirective('no-store');
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
