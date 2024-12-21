<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Hook\Hook;

/**
 * Facade for WordPress Action Hooks.
 *
 * Provides a fluent interface for working with WordPress action hooks,
 * including adding, removing, and executing actions.
 *
 * @method static mixed do(string $hook, mixed $args = null) Execute an action hook
 * @method static Hook add(string|array $hooks, \Closure|string|array $callback, int $priority = 10, int $accepted_args = 3) Add an action hook
 * @method static bool exists(string $hook) Check if an action exists
 * @method static Hook|false remove(string $hook, \Closure|string $callback, int $priority = 10) Remove an action hook
 *
 * @see \Pollora\Hook\Hook
 */
class Action extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.action';
    }
}
