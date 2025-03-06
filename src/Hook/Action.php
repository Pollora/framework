<?php

declare(strict_types=1);

namespace Pollora\Hook;

use Pollora\Hook\Contracts\Action as ActionInterface;

/**
 * WordPress Action Hook implementation.
 *
 * Provides functionality for working with WordPress action hooks,
 * allowing execution of actions at specific points in the application.
 */
class Action extends AbstractHook implements ActionInterface
{
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
