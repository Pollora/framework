<?php

declare(strict_types=1);

namespace Pollora\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestStore
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The application container instance.
         */
        protected Container $app
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->storeRequest($request);

        return $next($request);
    }

    /**
     * Store a clone of the request in the container.
     */
    protected function storeRequest(Request $request): void
    {
        $this->app->instance('laravel_request', clone $request);
    }
}
