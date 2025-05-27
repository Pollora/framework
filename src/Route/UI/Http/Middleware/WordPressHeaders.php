<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * WordPress headers middleware
 * 
 * Manages WordPress-specific HTTP headers for proper
 * integration with WordPress functionality.
 */
final class WordPressHeaders
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Apply WordPress headers to the response
        $this->applyWordPressHeaders($response, $request);
        
        return $response;
    }

    /**
     * Apply WordPress-specific headers to the response
     */
    private function applyWordPressHeaders($response, Request $request): void
    {
        if (!$response instanceof Response) {
            return;
        }

        // Set WordPress generator header
        $this->setGeneratorHeader($response);
        
        // Set content type headers based on WordPress context
        $this->setContentTypeHeaders($response, $request);
        
        // Set cache headers based on WordPress settings
        $this->setCacheHeaders($response, $request);
        
        // Set WordPress-specific security headers
        $this->setSecurityHeaders($response, $request);
        
        // Apply WordPress filters for custom headers
        $this->applyWordPressFilters($response, $request);
    }

    /**
     * Set WordPress generator header
     */
    private function setGeneratorHeader(Response $response): void
    {
        if (function_exists('get_bloginfo')) {
            $wpVersion = get_bloginfo('version');
            $response->headers->set('X-Powered-By', "WordPress/{$wpVersion}");
        }
    }

    /**
     * Set content type headers based on WordPress context
     */
    private function setContentTypeHeaders(Response $response, Request $request): void
    {
        // Check if this is a feed request
        if (function_exists('is_feed') && is_feed()) {
            $feedType = $this->determineFeedType();
            $response->headers->set('Content-Type', $this->getFeedContentType($feedType));
            return;
        }

        // Check if this is a robots.txt request
        if (function_exists('is_robots') && is_robots()) {
            $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
            return;
        }

        // Check if this is a JSON request or WordPress REST API
        if ($request->expectsJson() || $this->isWordPressRestApi($request)) {
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
            return;
        }

        // Default to HTML with WordPress charset
        $charset = function_exists('get_option') ? get_option('blog_charset', 'UTF-8') : 'UTF-8';
        $response->headers->set('Content-Type', "text/html; charset={$charset}");
    }

    /**
     * Set cache headers based on WordPress settings
     */
    private function setCacheHeaders(Response $response, Request $request): void
    {
        // Don't cache admin pages
        if (function_exists('is_admin') && is_admin()) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            return;
        }

        // Don't cache user-specific pages
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            return;
        }

        // Set cache headers for public content
        if ($this->isPublicContent($request)) {
            $maxAge = $this->getCacheMaxAge($request);
            $response->headers->set('Cache-Control', "public, max-age={$maxAge}");
            
            // Set ETag if possible
            $etag = $this->generateETag($request);
            if ($etag) {
                $response->headers->set('ETag', $etag);
            }
        }
    }

    /**
     * Set WordPress-specific security headers
     */
    private function setSecurityHeaders(Response $response, Request $request): void
    {
        // X-Frame-Options for WordPress admin
        if (function_exists('is_admin') && is_admin()) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // WordPress-specific security headers
        if (function_exists('apply_filters')) {
            $securityHeaders = apply_filters('wp_headers', []);
            foreach ($securityHeaders as $header => $value) {
                $response->headers->set($header, $value);
            }
        }
    }

    /**
     * Apply WordPress filters for custom headers
     */
    private function applyWordPressFilters(Response $response, Request $request): void
    {
        if (!function_exists('apply_filters')) {
            return;
        }

        // Apply general headers filter
        $headers = apply_filters('pollora_response_headers', $response->headers->all(), $request);
        
        foreach ($headers as $name => $value) {
            if (is_string($value) || (is_array($value) && count($value) === 1)) {
                $response->headers->set($name, is_array($value) ? $value[0] : $value);
            }
        }

        // Apply WordPress-specific filters
        do_action('pollora_set_headers', $response, $request);
    }

    /**
     * Determine the feed type
     */
    private function determineFeedType(): string
    {
        if (function_exists('get_query_var')) {
            $feedType = get_query_var('feed');
            if ($feedType) {
                return $feedType;
            }
        }

        return 'rss2'; // Default feed type
    }

    /**
     * Get content type for feed
     */
    private function getFeedContentType(string $feedType): string
    {
        return match ($feedType) {
            'rss' => 'application/rss+xml; charset=utf-8',
            'rss2' => 'application/rss+xml; charset=utf-8',
            'rdf' => 'application/rdf+xml; charset=utf-8',
            'atom' => 'application/atom+xml; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            default => 'application/rss+xml; charset=utf-8'
        };
    }

    /**
     * Check if this is a WordPress REST API request
     */
    private function isWordPressRestApi(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_contains($path, '/wp-json/') || str_contains($path, 'wp-json');
    }

    /**
     * Check if content should be publicly cached
     */
    private function isPublicContent(Request $request): bool
    {
        // Don't cache search results
        if (function_exists('is_search') && is_search()) {
            return false;
        }

        // Don't cache 404 pages
        if (function_exists('is_404') && is_404()) {
            return false;
        }

        // Cache static pages and posts
        if (function_exists('is_singular') && is_singular()) {
            return true;
        }

        // Cache archive pages
        if (function_exists('is_archive') && is_archive()) {
            return true;
        }

        return false;
    }

    /**
     * Get cache max age in seconds
     */
    private function getCacheMaxAge(Request $request): int
    {
        // Apply WordPress filter for custom cache duration
        if (function_exists('apply_filters')) {
            return apply_filters('pollora_cache_max_age', 3600, $request);
        }

        return 3600; // 1 hour default
    }

    /**
     * Generate ETag for the request
     */
    private function generateETag(Request $request): ?string
    {
        global $post;

        if (isset($post) && $post->post_modified) {
            return md5($post->ID . $post->post_modified);
        }

        return null;
    }
}