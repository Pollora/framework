<?php

namespace Pollora\Hook;

/**
 * Interface Hookable
 *
 * Defines the contract for WordPress hook implementations (actions and filters).
 * Ensures a consistent API between different hook types.
 *
 * @package Pollora\Hook
 */
interface Hookable
{
    /**
     * Adds a callback to a WordPress hook.
     *
     * @param string   $hook         The name of the WordPress hook.
     * @param callable $callback     The callback function to be executed.
     * @param int      $priority     Optional. The priority order in which the function will be executed.
     *                               Default 10.
     * @param int|null $acceptedArgs Optional. The number of arguments the function accepts.
     *                               If null, it will be auto-detected. Default null.
     * @return void
     */
    public function add(string $hook, callable $callback, int $priority = 10, ?int $acceptedArgs = null): void;

    /**
     * Removes a callback from a WordPress hook.
     *
     * @param string   $hook      The name of the WordPress hook.
     * @param callable $callback  The callback function to remove.
     * @param int      $priority  Optional. The priority of the function to remove.
     *                            Default 10.
     * @return void
     */
    public function remove(string $hook, callable $callback, int $priority = 10): void;

    /**
     * Checks if a hook, or specific callback on that hook, exists.
     *
     * @param string        $hook      The name of the WordPress hook.
     * @param callable|null $callback  Optional. The callback to check for.
     *                                 If null, only checks if the hook exists.
     * @param int|null      $priority  Optional. The priority of the function to check for.
     *                                 If null, checks all priorities.
     * @return bool True if the hook or specified callback exists, false otherwise.
     */
    public function exists(string $hook, callable $callback = null, int $priority = null): bool;
}
