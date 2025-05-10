<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Pollora\Hook\Domain\Contracts\Action as ActionContract;
use Pollora\Hook\Domain\Services\AbstractHook;

/**
 * Laravel/WordPress adapter for Action hooks.
 *
 * Implements the Action contract and delegates to WordPress functions.
 */
class Action extends AbstractHook implements ActionContract
{
    /**
     * Add one or multiple action hooks and register with WordPress.
     */
    public function add(string|array $hooks, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null): self
    {
        parent::add($hooks, $callback, $priority, $acceptedArgs);
        foreach ((array) $hooks as $hook) {
            $resolved = $this->resolveCallback($hook, $callback, $acceptedArgs);
            add_action($hook, $resolved['callable'], $priority, $resolved['args']);
        }

        return $this;
    }

    /**
     * Remove an action hook and unregister from WordPress.
     */
    public function remove(string $hook, callable|string|array|null $callback = null, int $priority = 10): self|false
    {
        $result = parent::remove($hook, $callback, $priority);
        if ($callback !== null) {
            $resolved = $this->resolveCallback($hook, $callback, null);
            remove_action($hook, $resolved['callable'], $priority);
        }

        return $result;
    }

    /**
     * Execute a WordPress action hook.
     *
     * @param  string  $hook  The action hook name to execute
     * @param  mixed  ...$args  Variable number of arguments to pass to the hook
     */
    public function do(string $hook, ...$args): self
    {
        do_action($hook, ...$args);

        return $this;
    }
}
