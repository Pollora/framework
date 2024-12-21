<?php

declare(strict_types=1);

namespace Pollora\Route\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Middleware to handle WordPress capability-based authorization.
 *
 * Ensures that the current user has the required WordPress capability
 * to access the requested route.
 */
class WordPressAuthorize
{
    /**
     * Handle the incoming request.
     *
     * Verifies if the current WordPress user has the specified capability.
     * Throws a 404 exception if the user is not authorized.
     *
     * @param Request $request The incoming request
     * @param Closure $next The next middleware handler
     * @param string $capability The WordPress capability to check (defaults to 'edit_posts')
     * @return Response
     * @throws HttpException When user is not authorized (404)
     */
    public function handle(Request $request, Closure $next, string $capability = 'edit_posts'): Response
    {
        if ($this->isUserAuthorized($capability)) {
            return $next($request);
        }

        throw new HttpException(404);
    }

    /**
     * Check if the current user is authorized.
     *
     * Verifies that the user is both logged in and has the required capability.
     *
     * @param string $capability The WordPress capability to check
     * @return bool True if user is authorized, false otherwise
     */
    private function isUserAuthorized(string $capability): bool
    {
        return is_user_logged_in() && current_user_can($capability);
    }
}
