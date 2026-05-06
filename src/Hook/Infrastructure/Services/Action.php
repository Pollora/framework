<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Pollora\Hook\Domain\Contracts\Action as ActionContract;
use Pollora\Hook\Domain\Services\AbstractHook;

/**
 * Laravel/WordPress adapter for Action hooks.
 *
 * Implements the Action contract and delegates to WordPress functions.
 * Uses hook-point methods (addHookEvent/removeHookEvent) to avoid
 * double callback resolution.
 */
class Action extends AbstractHook implements ActionContract
{
    /**
     * Register a hook event with WordPress.
     */
    protected function addHookEvent(string $hook, callable|string|array $callback, int $priority, int $acceptedArgs): void
    {
        parent::addHookEvent($hook, $callback, $priority, $acceptedArgs);
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Unregister a hook event from WordPress.
     */
    protected function removeHookEvent(string $hook, callable|string|array $callback, int $priority): void
    {
        remove_action($hook, $callback, $priority);
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
