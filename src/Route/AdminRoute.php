<?php

declare(strict_types=1);

namespace Pollora\Route;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router as IlluminateRouter;

class AdminRoute
{
    public function __construct(
        private readonly Request $request,
        private readonly IlluminateRouter $router
    ) {}

    public function get(): \Illuminate\Routing\Route
    {
        $wordpressUri = trim((string) config('app.wp.dir', 'cms'), '\/');
        $route = $this->router->any("$wordpressUri/wp-admin/{any?}", fn (): \Illuminate\Http\Response => new Response);

        $route->middleware('admin');
        $route->bind($this->request);

        return $route;
    }
}
