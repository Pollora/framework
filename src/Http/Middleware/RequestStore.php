<?php

declare(strict_types=1);

namespace Pollen\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequestStore
{
    public function handle(Request $request, Closure $next)
    {
        app()->instance('laravel_request', clone $request);

        return $next($request);
    }
}
