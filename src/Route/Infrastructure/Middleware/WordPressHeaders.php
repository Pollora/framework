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
 * and cache control directives.
 */
class WordPressHeaders
{
    /**
     * Framework name constant for headers.
     */
    private const FRAMEWORK_NAME = 'Pollora';

    /**
     * Framework header name constant.
     */
    private const FRAMEWORK_HEADER = 'X-Powered-By';

    /**
     * Handle the incoming request.
     *
     * Manages response headers including:
     * - Adding framework identification
     * - Cleaning up WordPress headers for non-authenticated requests
     * - Setting appropriate cache control directives
     *
     * @param  Request  $request  Incoming HTTP request
     * @param  Closure  $next  Next middleware handler
     * @return SymfonyResponse Modified response instance
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
            $response->setPublic();
            $response->headers->addCacheControlDirective('must-revalidate', true);
            $response->headers->addCacheControlDirective('max-age', '3600');
        }

        return $response;
    }

    /**
     * Add the framework identification header.
     *
     * @param  SymfonyResponse  $response  Response being modified
     */
    private function addFrameworkHeader(SymfonyResponse $response): void
    {
        $response->headers->set(self::FRAMEWORK_HEADER, self::FRAMEWORK_NAME);
    }

    /**
     * Determine if WordPress headers should be cleaned up.
     *
     * @param  Request  $request  Incoming request instance
     * @return bool True when headers should be removed
     */
    private function shouldCleanupHeaders(Request $request): bool
    {
        $route = $request->route();

        return $route &&
               method_exists($route, 'hasCondition') &&
               ! $route->hasCondition() &&
               $this->isWordPressFunctionAvailable('is_user_logged_in') &&
               ! is_user_logged_in();
    }

    /**
     * Remove WordPress-specific headers from the response.
     *
     * @param  SymfonyResponse  $response  Response to clean up
     */
    private function removeWordPressHeaders(SymfonyResponse $response): void
    {
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Expires');
        $response->headers->remove('Content-Type');
    }

    /**
     * Determine if public cache headers should be set.
     *
     * @return bool True when public caching is allowed
     */
    private function shouldSetPublicCache(): bool
    {
        return $this->isWordPressFunctionAvailable('is_user_logged_in') && ! is_user_logged_in();
    }

    /**
     * Check if a WordPress function is available.
     *
     * @param  string  $function  Function name to check
     * @return bool True if the function exists
     */
    private function isWordPressFunctionAvailable(string $function): bool
    {
        return function_exists($function);
    }
}
