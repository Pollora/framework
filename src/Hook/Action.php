<?php

declare(strict_types=1);

namespace Pollora\Hook;

/**
 * Class Action
 *
 * Manages WordPress actions with an optimized internal indexing system.
 * Provides a clean, Laravel-style interface for action management.
 */
final class Action extends AbstractHook
{
    /**
     * The WordPress function to use when adding a hook.
     *
     * @param  string  $hook  Hook name
     * @param  callable|string|array  $callback  Callback to execute
     * @param  int  $priority  Priority
     * @param  int  $acceptedArgs  Number of accepted args
     */
    protected function addHook(string $hook, callable|string|array $callback, int $priority, int $acceptedArgs): void
    {
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * The WordPress function to use when removing a hook.
     *
     * @param  string  $hook  Hook name
     * @param  callable|string|array  $callback  Callback to remove
     * @param  int  $priority  Priority
     */
    protected function removeHook(string $hook, callable|string|array $callback, int $priority): void
    {
        remove_action($hook, $callback, $priority);
    }

    /**
     * Executes all callbacks registered for a specific WordPress action hook.
     *
     * @param  string  $hook  The action hook name.
     * @param  mixed  ...$args  Optional arguments to pass to the callbacks.
     */
    public function do(string $hook, ...$args): void
    {
        do_action($hook, ...$args);
    }

    /**
     * Executes all callbacks registered for a specific WordPress action hook,
     * passing the arguments in an array.
     *
     * @param  string  $hook  The action hook name.
     * @param  array  $args  Arguments to pass to the callbacks.
     */
    public function doArray(string $hook, array $args = []): void
    {
        do_action_array($hook, $args);
    }

    /**
     * Executes all callbacks registered for a specific WordPress action hook once.
     *
     * @param  string  $hook  The action hook name.
     * @param  mixed  ...$args  Optional arguments to pass to the callbacks.
     */
    public function doOnce(string $hook, ...$args): void
    {
        do_action_once($hook, ...$args);
    }
}
