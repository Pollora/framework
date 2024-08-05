<?php

declare(strict_types=1);

namespace Pollen\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpFoundation\Response;

class RequestStore
{
    /**
     * The application container instance.
     *
     * @var Container
     */
    protected Container $app;

    /**
     * Create a new middleware instance.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->storeRequest($request);

        return $next($request);
    }

    /**
     * Store a clone of the request in the container.
     *
     * @param Request $request
     * @return void
     */
    protected function storeRequest(Request $request): void
    {
        $this->app->instance('laravel_request', clone $request);
    }
}
