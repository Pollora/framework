<?php

declare(strict_types=1);

namespace Pollen\Hook;

interface IHook
{
    /**
     * Add event using the WordPress hooks.
     *
     * @param  string  $hooks The hook name.
     * @param  array|string|\Closure  $callback Using a class method like so "MyClass@method"
     */
    public function add(string $hooks, array|string|\Closure $callback, int $priority = 10, int $accepted_args = 2): mixed;

    /**
     * Run all events registered with the hook.
     *
     * @param  string  $hook The event hook name.
     */
    public function run(string $hook, mixed $args = null): mixed;

    /**
     * Check if a registered hook exists.
     */
    public function exists(string $hook): bool;

    /**
     * Return the callback registered with the given hook.
     *
     * @param  string  $hook The hook name.
     */
    public function getCallback(string $hook): ?array;

    /**
     * Remove a defined action or filter.
     *
     * @param  string  $hook     The hook name.
     * @param  string|\Closure|null  $callback The callback to remove.
     * @param  int  $priority The priority number.
     */
    public function remove(string $hook, string|\Closure $callback = null, int $priority = 10): mixed;
}
