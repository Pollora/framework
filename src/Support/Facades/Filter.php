<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Hook\Hook;

/**
 * Facade for WordPress Filter Hooks.
 *
 * Provides a fluent interface for working with WordPress filter hooks,
 * including adding, removing, and applying filters.
 *
 * @method static mixed apply(string $hook, mixed $args = null) Apply a filter hook
 * @method static Hook add(string|array $hooks, \Closure|string|array $callback, int $priority = 10, int $accepted_args = 3) Add a filter hook
 * @method static bool exists(string $hook) Check if a filter exists
 * @method static Hook|false remove(string $hook, \Closure|string $callback, int $priority = 10) Remove a filter hook
 *
 * @see \Pollora\Hook\Hook
 */
class Filter extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Pollora\Hook\Infrastructure\Services\Filter::class;
    }
}
