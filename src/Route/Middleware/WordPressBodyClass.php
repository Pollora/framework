<?php

declare(strict_types=1);

namespace Pollen\Route\Middleware;

use Closure;
use Pollen\Hook\Filter;
use Pollen\Route\Route;

class WordPressBodyClass
{
    public function __construct(protected Filter $filter) {}

    public function handle($request, Closure $next)
    {
        $this->filter->add('body_class', $this->getBodyClassCallback($request->route()));
        return $next($request);
    }

    private function getBodyClassCallback(Route $route): Closure
    {
        return function (array $classes) use ($route): array {
            if ($route->hasCondition()) {
                return $classes;
            }

            $tokens = $this->getRouteTokens($route);

            if (!empty($tokens)) {
                return array_filter(array_merge($tokens, $classes), fn($class) => $class !== 'error404');
            }

            return $classes;
        };
    }

    private function getRouteTokens(Route $route): array
    {
        return array_filter(array_map(function ($token) use ($route) {
            switch ($token[0]) {
                case 'variable':
                    return $this->handleVariableToken($token, $route);
                case 'text':
                    return sanitize_title($token[1]);
                default:
                    return false;
            }
        }, array_reverse($route->getCompiled()->getTokens())));
    }

    private function handleVariableToken(array $token, Route $route): string|false
    {
        if (isset($token[3]) && $route->hasParameter($paramKey = $token[3])) {
            $param = $route->parameter($paramKey);
            return is_string($param) ? sprintf('%s-%s', $paramKey, sanitize_title($param)) : false;
        }
        return false;
    }
}
