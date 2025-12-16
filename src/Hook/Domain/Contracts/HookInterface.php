<?php

declare(strict_types=1);

namespace Pollora\Hook\Domain\Contracts;

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
     * @param  callable|string|array  $callback  The callback function
     * @param  int  $priority  Optional priority
     * @param  int|null  $acceptedArgs  Optional. Number of arguments the callback accepts
     */
    public function add(string|array $hooks, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null): self;

    /**
     * Remove a hook callback.
     *
     * @param  string  $hook  Hook name
     * @param  callable|string|array|null  $callback  Optional callback to remove
     * @param  int  $priority  Optional priority
     */
    public function remove(string $hook, callable|string|array|null $callback = null, int $priority = 10): self|false;

    /**
     * Check if a hook exists.
     *
     * @param  string  $hook  Hook name
     * @param  callable|null  $callback  Optional. Specific callback to check
     * @param  int|null  $priority  Optional. Specific priority to check
     */
    public function exists(string $hook, ?callable $callback = null, ?int $priority = null): bool;
}
