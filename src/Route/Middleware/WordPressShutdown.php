<?php

namespace Pollora\Route\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to handle WordPress shutdown actions within Laravel requests.
 *
 * This middleware ensures that WordPress shutdown actions are executed properly
 * when the response is of type "text/html".
 */
class WordPressShutdown
{
    /**
     * Content types that should be processed.
     *
     * @var array
     */
    protected static array $validContentTypes = ['text/html', 'text/html; charset=UTF-8'];

    /**
     * Handle an incoming request and process WordPress shutdown actions.
     *
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware in the pipeline.
     * @return Response The processed response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Early return if WordPress shutdown hook doesn't exist
        if (!function_exists('shutdown_action_hook')) {
            return $response;
        }

        // Get the Content-Type of the response
        $contentType = $response->headers->get('Content-Type', '');

        // Quick lookup for valid content types
        if (!$this->isHtmlResponse($contentType)) {
            return $response;
        }

        // Only modify the response content if the response is not a streaming response
        if (method_exists($response, 'getContent')) {
            // Get the original content only once
            $originalContent = $response->getContent();

            // Use output buffering only if necessary
            ob_start();
            echo $originalContent;
            shutdown_action_hook();
            $newContent = ob_get_clean();

            // Only set content if it has changed (avoids unnecessary operations)
            if ($newContent !== $originalContent) {
                $response->setContent($newContent);
            }
        }

        return $response;
    }

    /**
     * Check if the response is HTML.
     *
     * @param string $contentType
     * @return bool
     */
    protected function isHtmlResponse(string $contentType): bool
    {
        // Direct match is faster than strpos
        if (in_array($contentType, self::$validContentTypes, true)) {
            return true;
        }

        // Fallback to strpos for partial matches
        return strpos($contentType, 'text/html') !== false;
    }
}
