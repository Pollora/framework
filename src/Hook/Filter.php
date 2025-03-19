<?php

declare(strict_types=1);

namespace Pollora\Hook;

/**
 * Class Filter
 *
 * Manages WordPress filters with an optimized internal indexing system.
 * Provides a clean, Laravel-style interface for filter management.
 */
final class Filter extends AbstractHook
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
        add_filter($hook, $callback, $priority, $acceptedArgs);
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
        remove_filter($hook, $callback, $priority);
    }

    /**
     * Applies all registered callbacks for a filter hook to the given value.
     *
     * @param  string  $hook  The filter hook name.
     * @param  mixed  $value  The value to filter.
     * @param  mixed  ...$args  Optional additional arguments to pass to the callbacks.
     * @return mixed The filtered value after all callbacks are applied.
     */
    public function apply(string $hook, $value, ...$args): mixed
    {
        return apply_filters($hook, $value, ...$args);
    }

    /**
     * Applies all registered callbacks for a filter hook to the given value,
     * passing the arguments in an array.
     *
     * @param  string  $hook  The filter hook name.
     * @param  mixed  $value  The value to filter.
     * @param  array  $args  Additional arguments to pass to the callbacks.
     * @return mixed The filtered value after all callbacks are applied.
     */
    public function applyArray(string $hook, $value, array $args = []): mixed
    {
        return apply_filters_array($hook, array_merge([$value], $args));
    }
}
