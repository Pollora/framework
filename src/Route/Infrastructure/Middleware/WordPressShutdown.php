<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Middleware to handle WordPress shutdown hooks.
 *
 * In a standard WordPress lifecycle, the `shutdown` action fires during PHP's
 * shutdown phase after all output has been flushed. Plugins like Query Monitor
 * (debug toolbar at priority 9) and WP Rocket (cache processing) rely on this hook.
 *
 * In Pollora's Laravel-based lifecycle, this timing is problematic: Laravel's
 * Response::send() calls fastcgi_finish_request(), closing the client connection
 * before PHP shutdown. Any plugin output during shutdown is therefore lost.
 *
 * This middleware solves the problem by executing `do_action('shutdown')` early,
 * within a controlled output buffer, BEFORE the response is sent. Captured output
 * (e.g. Query Monitor toolbar) is injected before the closing `</body>` tag
 * for HTML responses. Non-HTML responses still trigger shutdown hooks for
 * side-effect processing (cache, cleanup) but discard any output.
 *
 * Double execution is prevented by removing all shutdown callbacks after firing.
 * WordPress's own `shutdown_action_hook()` (registered via register_shutdown_function
 * in wp-settings.php) still runs during PHP shutdown, but `do_action('shutdown')`
 * finds no callbacks. `wp_cache_close()`, called directly in `shutdown_action_hook()`,
 * remains unaffected.
 *
 * @see \Pollora\Route\Infrastructure\Providers\RouteServiceProvider::WORDPRESS_MIDDLEWARE
 */
class WordPressShutdown
{
    /**
     * Handle the incoming request.
     *
     * Processes WordPress shutdown hooks after the response is built but before
     * it is sent to the client. Any captured output is injected into HTML responses.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware handler in the pipeline
     * @return mixed The HTTP response, potentially with injected shutdown output
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (! $this->canProcessShutdown()) {
            return $response;
        }

        $shutdownOutput = $this->executeShutdownHooks();
        $this->preventDoubleShutdown();

        if ($shutdownOutput !== '' && $this->canInjectContent($response)) {
            $this->injectIntoResponse($response, $shutdownOutput);
        }

        return $response;
    }

    /**
     * Check if WordPress shutdown processing is available.
     *
     * Verifies that the required WordPress hook functions are loaded.
     * This guard prevents errors when WordPress is not fully bootstrapped
     * (e.g. during artisan commands or test suites).
     *
     * @return bool True if WordPress hook functions are available
     */
    private function canProcessShutdown(): bool
    {
        return function_exists('do_action') && function_exists('remove_action');
    }

    /**
     * Execute WordPress shutdown hooks within a controlled output buffer.
     *
     * The method follows this sequence:
     * 1. Removes `wp_ob_end_flush_all` (shutdown priority 1) to prevent it from
     *    flushing all buffer levels uncontrollably during capture
     * 2. Records the current output buffer level as a restoration baseline
     * 3. Opens a capture buffer and fires `do_action('shutdown')`
     * 4. Collapses any nested buffers opened by shutdown callbacks
     * 5. Captures and returns the aggregated output
     * 6. Restores output buffer depth to the pre-execution baseline
     *
     * If a plugin throws during shutdown, buffers are cleaned up gracefully
     * and an empty string is returned (following Laravel's PhpEngine pattern).
     *
     * @return string The captured shutdown output, or empty string on failure
     */
    private function executeShutdownHooks(): string
    {
        remove_action('shutdown', 'wp_ob_end_flush_all', 1);

        $baseLevel = ob_get_level();

        ob_start();

        try {
            do_action('shutdown');
        } catch (\Throwable) {
            while (ob_get_level() > $baseLevel) {
                ob_end_clean();
            }

            return '';
        }

        // Collapse any nested buffers opened by shutdown callbacks
        // into our capture buffer via ob_end_flush()
        while (ob_get_level() > $baseLevel + 1) {
            ob_end_flush();
        }

        $output = (string) ob_get_clean();

        // Restore output buffer depth to pre-execution baseline,
        // cleaning any WordPress buffers opened during bootstrap
        while (ob_get_level() > $baseLevel) {
            ob_end_clean();
        }

        return $output;
    }

    /**
     * Remove all shutdown hook callbacks to prevent double execution.
     *
     * WordPress registers `shutdown_action_hook()` via `register_shutdown_function()`
     * in wp-settings.php (line 164). This function calls `do_action('shutdown')`
     * then `wp_cache_close()`. By removing all callbacks from the `shutdown` hook,
     * the second `do_action('shutdown')` call during PHP shutdown becomes a no-op,
     * while `wp_cache_close()` (a direct function call) still executes normally.
     */
    private function preventDoubleShutdown(): void
    {
        remove_all_actions('shutdown');
    }

    /**
     * Check if the response can receive injected content.
     *
     * Returns false for responses that should not be modified:
     * - Non-Symfony response objects (raw strings, etc.)
     * - Streamed or binary file responses (no accessible content buffer)
     * - Redirects (3xx), informational (1xx), and empty (204/304) responses
     * - Non-HTML content types (JSON, XML, plain text, etc.)
     *
     * @param  mixed  $response  The response to evaluate
     * @return bool True if the response is a modifiable HTML response
     */
    private function canInjectContent(mixed $response): bool
    {
        if (! $response instanceof SymfonyResponse) {
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

        return $contentType === '' || str_contains($contentType, 'text/html');
    }

    /**
     * Inject shutdown output into the response body before the closing </body> tag.
     *
     * If a `</body>` tag is found (case-insensitive), the output is inserted
     * immediately before it to maintain valid HTML structure. Otherwise, the
     * output is appended to the end of the response content as a fallback.
     *
     * @param  SymfonyResponse  $response  The response to modify
     * @param  string  $output  The shutdown output to inject
     */
    private function injectIntoResponse(SymfonyResponse $response, string $output): void
    {
        $content = $response->getContent();

        if ($content === false) {
            return;
        }

        $bodyPos = strripos($content, '</body>');

        $response->setContent(
            $bodyPos !== false
                ? substr($content, 0, $bodyPos) . $output . substr($content, $bodyPos)
                : $content . $output
        );
    }
}
