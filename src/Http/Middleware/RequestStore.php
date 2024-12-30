<?php

declare(strict_types=1);

namespace Pollora\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RequestStore
 * 
 * Middleware that stores a clone of the current request in the application container.
 * Allows access to the original request throughout the application lifecycle.
 */
class RequestStore
{
    /**
     * Create a new middleware instance.
     *
     * @param Container $app The application container instance used to store the request
     */
    public function __construct(
        /**
         * The application container instance.
         */
        protected Container $app
    ) {}

    /**
     * Handle an incoming request.
     * 
     * Stores a clone of the request in the container and passes it through the middleware stack.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware handler
     * @return Response The HTTP response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->storeRequest($request);

        return $next($request);
    }

    /**
     * Store a clone of the request in the container.
     * 
     * Creates a copy of the request and stores it in the container
     * under the 'laravel_request' key for later retrieval.
     *
     * @param Request $request The request to store
     * @return void
     */
    protected function storeRequest(Request $request): void
    {
        $this->app->instance('laravel_request', clone $request);
    }
}
