<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware to handle WordPress shutdown hooks.
 *
 * Ensures that WordPress shutdown hooks are properly executed
 * for routes that require WordPress functionality.
 */
class WordPressShutdown
{
    /**
     * Handle the incoming request.
     *
     * Ensures WordPress shutdown hooks are executed after the response.
     *
     * @param  Request  $request  Incoming HTTP request
     * @param  Closure  $next  Next middleware handler
     * @return mixed Response from subsequent middleware
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        // Register a shutdown function to ensure WordPress cleanup
        if (function_exists('wp_ob_end_flush_all')) {
            register_shutdown_function(function (): void {
                // Clean up any output buffers WordPress might have opened
                if (function_exists('wp_ob_end_flush_all')) {
                    wp_ob_end_flush_all();
                }

                // Execute WordPress shutdown hooks
                if (function_exists('do_action')) {
                    do_action('shutdown');
                }
            });
        }

        return $response;
    }
}
