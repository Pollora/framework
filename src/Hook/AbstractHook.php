<?php

declare(strict_types=1);

namespace Pollora\Hook;

use Illuminate\Support\Collection;
use Pollora\Hook\Contracts\HookInterface;

/**
 * Abstract base class for WordPress hooks implementation.
 * 
 * Provides common functionality for managing WordPress hooks (actions and filters)
 * with a fluent interface for adding, removing, and checking hook existence.
 */
abstract class AbstractHook implements HookInterface
{
    /**
     * Collection of registered hooks.
     *
     * @var Collection
     */
    protected Collection $hooks;

    /**
     * Initialize a new hook instance.
     */
    public function __construct()
    {
        $this->hooks = collect();
    }

    /**
     * Add one or multiple hooks with a callback.
     *
     * @param string|array $hooks        Hook name or array of hook names
     * @param callable     $callback     Function to be called when hook is triggered
     * @param int         $priority     Optional. Priority of the hook (default: 10)
     * @param int         $acceptedArgs Optional. Number of arguments the callback accepts (default: 2)
     * @return self
     */
    public function add(string|array $hooks, callable $callback, int $priority = 10, int $acceptedArgs = 2): self
    {
        foreach ((array) $hooks as $hook) {
            $this->addHookEvent($hook, $callback, $priority, $acceptedArgs);
        }

        return $this;
    }

    /**
     * Remove a hook or all hooks for a specific hook name.
     *
     * @param string        $hook     The hook name to remove
     * @param callable|null $callback Optional. Specific callback to remove
     * @param int          $priority Optional. Priority of the hook to remove
     * @return self
     */
    public function remove(string $hook, ?callable $callback = null, int $priority = 10): self
    {
        if ($callback === null) {
            $this->hooks->forget($hook);
        } else {
            $this->removeHookEvent($hook, $callback, $priority);
        }

        return $this;
    }

    /**
     * Check if a hook exists.
     *
     * @param string $hook The hook name to check
     * @return bool True if the hook exists, false otherwise
     */
    public function exists(string $hook): bool
    {
        return $this->hooks->has($hook);
    }

    /**
     * Add a single hook event to WordPress.
     *
     * @param string   $hook         The hook name
     * @param callable $callback     The callback function
     * @param int      $priority     The priority of the hook
     * @param int      $acceptedArgs Number of arguments the callback accepts
     * @return void
     */
    protected function addHookEvent(string $hook, callable $callback, int $priority, int $acceptedArgs): void
    {
        $this->hooks->put($hook, [$callback, $priority, $acceptedArgs]);
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Remove a single hook event from WordPress.
     *
     * @param string   $hook     The hook name
     * @param callable $callback The callback function
     * @param int      $priority The priority of the hook
     * @return void
     */
    protected function removeHookEvent(string $hook, callable $callback, int $priority): void
    {
        remove_filter($hook, $callback, $priority);
        $this->hooks->forget($hook);
    }
}
