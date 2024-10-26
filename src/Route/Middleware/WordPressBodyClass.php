<?php

declare(strict_types=1);

namespace Pollora\Route\Middleware;

use Closure;
use Pollora\Hook\Filter;
use Pollora\Route\Route;

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

            if ($tokens !== []) {
                return array_filter(array_merge($tokens, $classes), fn ($class): bool => $class !== 'error404');
            }

            return $classes;
        };
    }

    private function getRouteTokens(Route $route): array
    {
        return array_filter(array_map(fn ($token) => match ($token[0]) {
            'variable' => $this->handleVariableToken($token, $route),
            'text' => sanitize_title($token[1]),
            default => false,
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
