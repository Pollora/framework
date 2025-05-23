<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Closure;
use Illuminate\Http\Request;
use Pollora\Route\Domain\Contracts\AuthorizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Laravel middleware to handle WordPress capability-based authorization.
 *
 * Ensures that the current user has the required WordPress capability
 * to access the requested route.
 */
class LaravelAuthorizeMiddleware
{
    /**
     * Create a new authorize middleware instance.
     */
    public function __construct(
        private readonly AuthorizerInterface $authorizer
    ) {}

    /**
     * Handle the incoming request.
     *
     * Verifies if the current WordPress user has the specified capability.
     * Throws a 404 exception if the user is not authorized.
     *
     * @param  Request  $request  The incoming request
     * @param  Closure  $next  The next middleware handler
     * @param  string  $capability  The WordPress capability to check (defaults to 'edit_posts')
     * @return Response The response
     *
     * @throws HttpException When user is not authorized (404)
     */
    public function handle(Request $request, Closure $next, string $capability = 'edit_posts'): Response
    {
        if ($this->authorizer->isAuthorized($capability)) {
            return $next($request);
        }

        throw new HttpException(404);
    }
}
