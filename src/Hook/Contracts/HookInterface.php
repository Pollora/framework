<?php

declare(strict_types=1);

namespace Pollora\Hook\Contracts;

/**
 * Base interface for WordPress hooks.
 *
 * Defines the common contract for both action and filter hooks.
 */
interface HookInterface
{
    /**
     * Add a hook callback.
     *
     * @param  string|array  $hooks  Hook name(s)
     * @param  callable  $callback  The callback function
     * @param  int  $priority  Optional priority
     * @param  int  $acceptedArgs  Optional number of arguments
     */
    public function add(string|array $hooks, callable|string|array $callback, int $priority = 10): self;

    /**
     * Remove a hook callback.
     *
     * @param  string  $hook  Hook name
     * @param  callable|null  $callback  Optional callback to remove
     * @param  int  $priority  Optional priority
     */
    public function remove(string $hook, callable|string|array|null $callback = null, int $priority = 10): self|false;

    /**
     * Check if a hook exists.
     *
     * @param  string  $hook  Hook name
     */
    public function exists(string $hook): bool;
}
