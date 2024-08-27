<?php

declare(strict_types=1);

namespace Pollen\Route;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router as IlluminateRouter;

class AdminRoute
{
    public function __construct(
        private Request $request,
        private IlluminateRouter $router
    ) {}

    public function get(): \Illuminate\Routing\Route
    {
        $wordpressUri = trim(config('app.wp.dir', 'cms'), '\/');
        $route = $this->router->any("$wordpressUri/wp-admin/{any?}", fn () => new Response);

        $route->middleware('admin');
        $route->bind($this->request);

        return $route;
    }
}
