<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Closure;
use Illuminate\Http\Request;
use Pollora\Route\Domain\Contracts\AuthorizerInterface;
use Pollora\Route\Domain\Contracts\HeaderManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel middleware to manage HTTP headers for WordPress responses.
 *
 * Handles framework-specific headers, WordPress header cleanup,
 * and cache control directives.
 */
class LaravelHeadersMiddleware
{
    /**
     * Create a new headers middleware instance.
     */
    public function __construct(
        private readonly HeaderManagerInterface $headerManager,
        private readonly AuthorizerInterface $authorizer
    ) {}

    /**
     * Handle the incoming request.
     *
     * Manages response headers including:
     * - Adding framework identification
     * - Cleaning up WordPress headers for non-authenticated requests
     * - Setting appropriate cache control directives
     *
     * @param  Request  $request  The incoming request
     * @param  Closure  $next  The next middleware handler
     * @return Response The response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        // Get current header array from Symfony Response
        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            $headers[$name] = $response->headers->get($name);
        }

        // Check if the user is logged in
        $isUserLoggedIn = $this->authorizer->isLoggedIn();

        // Add framework header
        $headers = $this->headerManager->addIdentificationHeaders($headers);

        // Determine if this is a WordPress route
        $route = $request->route();
        $isWordPressRoute = ($route instanceof Route && $route->hasCondition());

        // Cleanup WordPress headers
        $headers = $this->headerManager->cleanupWordPressHeaders($headers, $isWordPressRoute, $isUserLoggedIn);

        // Add cache control directives
        $headers = $this->headerManager->addCacheControlDirectives($headers, $isUserLoggedIn);

        // Apply the modified headers to the response
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
