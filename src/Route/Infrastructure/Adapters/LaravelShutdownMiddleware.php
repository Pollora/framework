<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Closure;
use Illuminate\Http\Request;
use Pollora\Route\Domain\Contracts\ShutdownHandlerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel middleware to handle WordPress shutdown actions.
 *
 * This middleware ensures that WordPress shutdown actions are executed
 * properly when the response is of a valid content type.
 */
class LaravelShutdownMiddleware
{
    /**
     * Create a new shutdown middleware instance.
     */
    public function __construct(
        private readonly ShutdownHandlerInterface $shutdownHandler
    ) {}

    /**
     * Handle an incoming request and process WordPress shutdown actions.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     * @return Response The processed response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only modify responses that are Response objects and can have content
        if (! method_exists($response, 'getContent')) {
            return $response;
        }

        // Get the Content-Type of the response
        $contentType = $response->headers->get('Content-Type', '');

        // Only process applicable content types
        if (! $this->shutdownHandler->shouldProcessContentType($contentType)) {
            return $response;
        }

        // Get the original content and apply shutdown actions
        $originalContent = $response->getContent();
        $newContent = $this->shutdownHandler->executeShutdownActions($originalContent);

        // Only set content if it has changed (avoids unnecessary operations)
        if ($newContent !== $originalContent) {
            $response->setContent($newContent);
        }

        return $response;
    }
}
