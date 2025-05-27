<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * WordPress shutdown middleware
 *
 * Handles WordPress shutdown hooks and cleanup for proper
 * integration with WordPress functionality.
 */
final class WordPressShutdown
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Perform WordPress shutdown actions
        $this->performWordPressShutdown($request, $response);

        return $response;
    }

    /**
     * Perform WordPress shutdown actions
     */
    private function performWordPressShutdown(Request $request, $response): void
    {
        // Execute WordPress shutdown hooks
        $this->executeShutdownHooks($request, $response);
    }

    /**
     * Execute WordPress shutdown hooks
     */
    private function executeShutdownHooks(Request $request, $response): void
    {
        if (!function_exists('do_action')) {
            return;
        }

        // Execute the main WordPress shutdown action
        do_action('shutdown');

        // Execute Pollora-specific shutdown actions
        do_action('pollora_shutdown', $request, $response);

        // Execute route-specific shutdown actions
        do_action('pollora_route_shutdown', $request, $response);
    }
}
