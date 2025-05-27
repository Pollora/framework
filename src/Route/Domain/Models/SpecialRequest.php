<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

/**
 * Value object for WordPress special requests
 * 
 * Represents special WordPress requests like robots.txt, favicon.ico,
 * feeds, and trackbacks that require special handling.
 */
final class SpecialRequest
{
    private const TYPES = [
        'robots' => 'is_robots',
        'favicon' => 'is_favicon',
        'feed' => 'is_feed',
        'trackback' => 'is_trackback',
        'xmlrpc' => 'is_xmlrpc',
        'pingback' => 'is_pingback'
    ];

    private function __construct(
        private readonly string $type,
        private readonly array $context = []
    ) {}

    /**
     * Create a SpecialRequest from context if applicable
     */
    public static function fromContext(array $context): ?self
    {
        foreach (self::TYPES as $type => $condition) {
            if (function_exists($condition) && $condition()) {
                return new self($type, $context);
            }
        }

        // Check for direct URI patterns as fallback
        $uri = $context['uri'] ?? '';
        
        if (self::matchesRobotsPattern($uri)) {
            return new self('robots', $context);
        }
        
        if (self::matchesFaviconPattern($uri)) {
            return new self('favicon', $context);
        }

        if (self::matchesFeedPattern($uri)) {
            return new self('feed', $context);
        }

        if (self::matchesTrackbackPattern($uri)) {
            return new self('trackback', $context);
        }

        return null;
    }

    /**
     * Create a specific special request type
     */
    public static function create(string $type, array $context = []): ?self
    {
        if (!array_key_exists($type, self::TYPES)) {
            return null;
        }

        return new self($type, $context);
    }

    /**
     * Get the request type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the WordPress condition function name
     */
    public function getConditionFunction(): string
    {
        return self::TYPES[$this->type];
    }

    /**
     * Get the request context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Check if this is a valid special request
     */
    public function isValid(): bool
    {
        $condition = $this->getConditionFunction();
        
        return function_exists($condition) && $condition();
    }

    /**
     * Check if this request should be handled by WordPress default behavior
     */
    public function shouldUseWordPressDefault(): bool
    {
        // Some special requests should always use WordPress default
        return in_array($this->type, ['xmlrpc', 'pingback'], true);
    }

    /**
     * Get the priority for this special request
     */
    public function getPriority(): int
    {
        return match ($this->type) {
            'robots' => 2000,
            'favicon' => 1900,
            'feed' => 1800,
            'trackback' => 1700,
            'xmlrpc' => 1600,
            'pingback' => 1500,
            default => 1000
        };
    }

    /**
     * Get all supported special request types
     */
    public static function getSupportedTypes(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Check if a type is supported
     */
    public static function isTypeSupported(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }

    /**
     * Convert to route condition
     */
    public function toRouteCondition(): RouteCondition
    {
        return RouteCondition::fromWordPressTag($this->getConditionFunction());
    }

    /**
     * Get the expected response content type
     */
    public function getExpectedContentType(): ?string
    {
        return match ($this->type) {
            'robots' => 'text/plain',
            'favicon' => 'image/x-icon',
            'feed' => 'application/rss+xml',
            'trackback' => 'text/xml',
            'xmlrpc' => 'text/xml',
            default => null
        };
    }

    /**
     * Check if URI matches robots.txt pattern
     */
    private static function matchesRobotsPattern(string $uri): bool
    {
        return str_ends_with($uri, '/robots.txt') || $uri === 'robots.txt';
    }

    /**
     * Check if URI matches favicon.ico pattern
     */
    private static function matchesFaviconPattern(string $uri): bool
    {
        return str_ends_with($uri, '/favicon.ico') || $uri === 'favicon.ico';
    }

    /**
     * Check if URI matches feed pattern
     */
    private static function matchesFeedPattern(string $uri): bool
    {
        return str_contains($uri, '/feed') || $uri === 'feed';
    }

    /**
     * Check if URI matches trackback pattern
     */
    private static function matchesTrackbackPattern(string $uri): bool
    {
        return str_contains($uri, '/trackback') || $uri === 'trackback';
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'condition_function' => $this->getConditionFunction(),
            'priority' => $this->getPriority(),
            'content_type' => $this->getExpectedContentType(),
            'use_wordpress_default' => $this->shouldUseWordPressDefault(),
            'context' => $this->context,
        ];
    }
}