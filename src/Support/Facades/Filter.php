<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Filter Hooks.
 *
 * Provides a fluent interface for working with WordPress filter hooks,
 * including adding, removing, and applying filters.
 *
 * @method static self add(string|array $hooks, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null) Add a filter hook
 * @method static mixed apply(string $hook, mixed $value, mixed ...$args) Apply a filter hook
 * @method static bool exists(string $hook, ?callable $callback = null, ?int $priority = null) Check if a filter exists
 * @method static self|false remove(string $hook, callable|string|array|null $callback = null, int $priority = 10) Remove a filter hook
 * @method static array|null callbacks(string $hook) Get registered callbacks for a hook
 *
 * @see \Pollora\Hook\Domain\Contracts\Filter
 */
class Filter extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Pollora\Hook\Domain\Contracts\Filter::class;
    }
}
