<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware to manage HTTP headers for WordPress responses.
 *
 * Handles framework-specific headers, WordPress header cleanup,
 * and cache control directives for non-authenticated visitors.
 */
class WordPressHeaders
{
    private const string FRAMEWORK_NAME = 'Pollora';

    private const string FRAMEWORK_HEADER = 'X-Powered-By';

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (! $response instanceof SymfonyResponse) {
            return $response;
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

    private function addFrameworkHeader(SymfonyResponse $response): void
    {
        $response->headers->set(self::FRAMEWORK_HEADER, self::FRAMEWORK_NAME);
    }

    private function shouldCleanupHeaders(Request $request): bool
    {
        $route = $request->route();

        return $route &&
               method_exists($route, 'hasCondition') &&
               ! $route->hasCondition() &&
               function_exists('is_user_logged_in') &&
               ! is_user_logged_in();
    }

    private function removeWordPressHeaders(SymfonyResponse $response): void
    {
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Expires');
        $response->headers->remove('Content-Type');
    }

    private function shouldSetPublicCache(): bool
    {
        return function_exists('is_user_logged_in') && ! is_user_logged_in();
    }

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
