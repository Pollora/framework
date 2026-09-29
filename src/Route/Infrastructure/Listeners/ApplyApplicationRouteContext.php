<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Listeners;

use Closure;
use Illuminate\Routing\Events\RouteMatched;
use Pollora\Route\Infrastructure\Models\Route;

/**
 * Gives a route the application owns a neutral WordPress context.
 *
 * WordPress resolves every request against its own rewrite rules before Laravel
 * routes it. A URL that only a Laravel route knows (`Route::get('/dashboard')`)
 * therefore comes out of that resolution as a 404: `is_404()` is true, `<body>`
 * carries `error404` and the document title reads "Page not found", over a
 * response that is a perfectly good 200.
 *
 * WordPress's verdict is only meaningful where WordPress answers: a
 * `Route::wp()` route and the template-hierarchy fallback are both flagged as
 * WordPress routes and left alone, so a real 404 keeps its `error404` class.
 * Every other route gets the 404 state cleared and its own URI segments as body
 * classes (`/dashboard/{tab}` → `dashboard tab-settings`).
 */
final class ApplyApplicationRouteContext
{
    public function handle(RouteMatched $event): void
    {
        $route = $event->route;

        if (! $route instanceof Route || $route->isWordPressRoute()) {
            return;
        }

        $this->clearNotFoundState();

        if (function_exists('add_filter')) {
            add_filter('body_class', $this->bodyClassCallback($route));
        }
    }

    /**
     * WordPress's "not found" verdict on the request is meaningless for a URL Laravel owns.
     */
    private function clearNotFoundState(): void
    {
        $query = $GLOBALS['wp_query'] ?? null;

        if (is_object($query) && property_exists($query, 'is_404')) {
            $query->is_404 = false;
        }
    }

    /**
     * @return Closure(array<int, string>): array<int, string>
     */
    private function bodyClassCallback(Route $route): Closure
    {
        return fn (array $classes): array => array_merge($this->routeTokens($route), $classes);
    }

    /**
     * @return array<int, string>
     */
    private function routeTokens(Route $route): array
    {
        $compiled = $route->getCompiled();

        if (! $compiled || ! method_exists($compiled, 'getTokens')) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $token): string|false => match ($token[0]) {
                'variable' => $this->variableToken($token, $route),
                'text' => $this->sanitizeClass($token[1]),
                default => false,
            },
            array_reverse($compiled->getTokens())
        )));
    }

    /**
     * @param  array<int, mixed>  $token
     */
    private function variableToken(array $token, Route $route): string|false
    {
        if (isset($token[3]) && $route->hasParameter($parameter = $token[3])) {
            $value = $route->parameter($parameter);

            return is_string($value) ? sprintf('%s-%s', $parameter, $this->sanitizeClass($value)) : false;
        }

        return false;
    }

    private function sanitizeClass(string $text): string
    {
        // A text token keeps the slash that precedes it: "/dashboard".
        $text = trim($text, '/');

        if (function_exists('sanitize_title')) {
            return sanitize_title($text);
        }

        return strtolower((string) preg_replace('/[^a-zA-Z0-9\-_]/', '-', trim($text)));
    }
}
