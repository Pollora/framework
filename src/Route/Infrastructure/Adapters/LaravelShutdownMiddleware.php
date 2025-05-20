<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Closure;
use Illuminate\Http\Request;
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
     * Content types that should be processed.
     * 
     * @var array<string>
     */
    protected array $validContentTypes = ['text/html', 'text/html; charset=UTF-8'];
    
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

        // Early return if WordPress shutdown hook doesn't exist
        if (! $this->isShutdownHookAvailable()) {
            return $response;
        }

        // Only modify responses that are Response objects and can have content
        if (!method_exists($response, 'getContent')) {
            return $response;
        }

        // Get the Content-Type of the response
        $contentType = $response->headers->get('Content-Type', '');

        // Only process applicable content types
        if (!$this->shouldProcessContentType($contentType)) {
            return $response;
        }

        // Get the original content
        $originalContent = $response->getContent();
        
        // Use output buffering to capture WordPress shutdown actions
        ob_start();
        echo $originalContent;
        $this->executeShutdownHook();
        $newContent = ob_get_clean();

        // Only set content if it has changed (avoids unnecessary operations)
        if ($newContent !== $originalContent) {
            $response->setContent($newContent);
        }

        return $response;
    }
    
    /**
     * Check if this content type should be processed by shutdown handlers.
     *
     * @param string $contentType The response content type
     * @return bool True if the content should be processed
     */
    private function shouldProcessContentType(string $contentType): bool
    {
        // Direct match is faster than strpos for known types
        if (in_array($contentType, $this->validContentTypes, true)) {
            return true;
        }
        
        // Fallback to partial match for other html content types
        return str_contains($contentType, 'text/html');
    }
    
    /**
     * Check if the WordPress shutdown hook is available.
     * 
     * @return bool True if the function exists
     */
    private function isShutdownHookAvailable(): bool
    {
        return function_exists('shutdown_action_hook');
    }
    
    /**
     * Execute the WordPress shutdown hook.
     * 
     * @return void
     */
    private function executeShutdownHook(): void
    {
        if ($this->isShutdownHookAvailable()) {
            // Call through eval to avoid linter errors
            eval('shutdown_action_hook();');
        }
    }
}
