<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Action Hooks.
 *
 * Provides a fluent interface for working with WordPress action hooks,
 * including adding, removing, and executing actions.
 *
 * @method static self add(string|array $hooks, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null) Add an action hook
 * @method static self do(string $hook, mixed ...$args) Execute an action hook
 * @method static bool exists(string $hook, ?callable $callback = null, ?int $priority = null) Check if an action exists
 * @method static self|false remove(string $hook, callable|string|array|null $callback = null, int $priority = 10) Remove an action hook
 * @method static array|null callbacks(string $hook) Get registered callbacks for a hook
 *
 * @see \Pollora\Hook\Domain\Contracts\Action
 */
class Action extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Pollora\Hook\Domain\Contracts\Action::class;
    }
}
