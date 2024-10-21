<?php

declare(strict_types=1);

namespace Pollora\Route\Middleware;

use Closure;
use Pollora\Route\Router;

class WordPressBindings
{
    public function __construct(private readonly Router $router) {}

    public function handle($request, Closure $next)
    {
        $route = $request->route();

        if ($route->hasCondition()) {
            $this->router->addWordPressBindings($route);
        }

        return $next($request);
    }
}
