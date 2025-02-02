<?php

declare(strict_types=1);

namespace Pollora\Hook\Contracts;

/**
 * Interface for WordPress Action hooks.
 *
 * Defines the contract for executing WordPress actions.
 */
interface ActionInterface extends HookInterface
{
    /**
     * Execute a WordPress action hook.
     *
     * @param  string  $hook  The action hook name
     * @param  mixed  ...$args  Additional arguments
     */
    public function do(string $hook, ...$args): self;
}
