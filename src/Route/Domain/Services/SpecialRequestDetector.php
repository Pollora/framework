<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Services;

use Pollora\Route\Domain\Models\SpecialRequest;

/**
 * Service for detecting WordPress special requests
 * 
 * Identifies special WordPress requests that need to be handled
 * differently from regular page requests.
 */
final class SpecialRequestDetector
{
    /**
     * URI patterns for special requests
     */
    private const URI_PATTERNS = [
        'robots' => ['/robots\.txt$/', '/robots\.txt\?/', 'robots.txt'],
        'favicon' => ['/favicon\.ico$/', '/favicon\.ico\?/', 'favicon.ico'],
        'sitemap' => ['/sitemap.*\.xml$/', '/sitemap.*\.xml\?/'],
        'feed' => ['/feed\/?$/', '/.*\/feed\/?$/'],
        'rss' => ['/.*\.rss$/', '/rss\/?$/'],
        'atom' => ['/.*\.atom$/', '/atom\/?$/'],
    ];

    /**
     * WordPress conditional functions for detection
     */
    private const CONDITION_FUNCTIONS = [
        'robots' => 'is_robots',
        'favicon' => 'is_favicon',
        'feed' => 'is_feed',
        'trackback' => 'is_trackback',
        'xmlrpc' => 'is_xmlrpc',
        'pingback' => 'is_pingback',
    ];

    /**
     * Detect special request from context
     * 
     * @param array $context Request context including URI, method, etc.
     * @return SpecialRequest|null The detected special request or null
     */
    public function detect(array $context): ?SpecialRequest
    {
        // First try WordPress conditional functions
        $request = $this->detectByWordPressConditions($context);
        if ($request) {
            return $request;
        }

        // Fallback to URI pattern matching
        return $this->detectByUriPattern($context);
    }

    /**
     * Check if a request is a special request
     * 
     * @param array $context Request context
     * @return bool True if request is special
     */
    public function isSpecialRequest(array $context): bool
    {
        return $this->detect($context) !== null;
    }

    /**
     * Get all possible special request types
     * 
     * @return array Array of special request types
     */
    public function getSupportedTypes(): array
    {
        return array_unique(array_merge(
            array_keys(self::CONDITION_FUNCTIONS),
            array_keys(self::URI_PATTERNS)
        ));
    }

    /**
     * Detect special request type from URI
     * 
     * @param string $uri The request URI
     * @return string|null The detected type or null
     */
    public function detectTypeFromUri(string $uri): ?string
    {
        foreach (self::URI_PATTERNS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (is_string($pattern) && str_contains($uri, $pattern)) {
                    return $type;
                }
                
                if (is_string($pattern) && str_starts_with($pattern, '/') && preg_match($pattern, $uri)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Check if URI matches a specific special request type
     * 
     * @param string $uri The URI to check
     * @param string $type The special request type
     * @return bool True if URI matches the type
     */
    public function uriMatchesType(string $uri, string $type): bool
    {
        if (!isset(self::URI_PATTERNS[$type])) {
            return false;
        }

        $patterns = self::URI_PATTERNS[$type];
        
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && str_contains($uri, $pattern)) {
                return true;
            }
            
            if (is_string($pattern) && str_starts_with($pattern, '/') && preg_match($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the priority for a special request type
     * 
     * @param string $type The special request type
     * @return int Priority score (higher = more important)
     */
    public function getTypePriority(string $type): int
    {
        return match ($type) {
            'robots' => 2000,
            'favicon' => 1900,
            'sitemap' => 1800,
            'feed', 'rss', 'atom' => 1700,
            'trackback' => 1600,
            'xmlrpc' => 1500,
            'pingback' => 1400,
            default => 1000
        };
    }

    /**
     * Check if a special request should bypass normal routing
     * 
     * @param string $type The special request type
     * @return bool True if should bypass normal routing
     */
    public function shouldBypassRouting(string $type): bool
    {
        // Some special requests should always be handled by WordPress
        return in_array($type, ['xmlrpc', 'pingback'], true);
    }

    /**
     * Detect special request using WordPress conditional functions
     * 
     * @param array $context Request context
     * @return SpecialRequest|null The detected request
     */
    private function detectByWordPressConditions(array $context): ?SpecialRequest
    {
        foreach (self::CONDITION_FUNCTIONS as $type => $function) {
            if (function_exists($function) && $function()) {
                return SpecialRequest::create($type, $context);
            }
        }

        return null;
    }

    /**
     * Detect special request using URI pattern matching
     * 
     * @param array $context Request context
     * @return SpecialRequest|null The detected request
     */
    private function detectByUriPattern(array $context): ?SpecialRequest
    {
        $uri = $context['uri'] ?? $context['path'] ?? '';
        
        if (empty($uri)) {
            return null;
        }

        $type = $this->detectTypeFromUri($uri);
        
        if ($type) {
            return SpecialRequest::create($type, $context);
        }

        return null;
    }
}